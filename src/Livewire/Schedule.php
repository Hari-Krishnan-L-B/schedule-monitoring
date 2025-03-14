<?php

declare(strict_types=1);

namespace Hari\Laravel\Pulse\Schedule\Livewire;

use function Safe\preg_replace;

use Closure;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule as IlluminateSchedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use ReflectionClass;
use ReflectionFunction;
use DB;

#[Lazy]
class Schedule extends Card
{
    #[Url(as: 'selectedcountry')]
    public string $selectedcountry = 'ALL'; // Default to 'ALL' to show all

    public function render(ConsoleKernel $kernel, IlluminateSchedule $schedule): View
    {
        $kernel->bootstrap();

        $timezone = new DateTimeZone(config('app.timezone')); 

        $events = collect($schedule->events())->map(function (Event $event): array {
            $command = $this->getCommand($event);

            $result = DB::select("SELECT created_at FROM monitored_scheduled_tasks WHERE name = ?", [$command]);
            $created_at = !empty($result) ? $result[0]->created_at : null;

            return [
                'command' => $command,
                'expression' => $this->getExpression($event),
                'next_due' => $this->getNextDueDateForEvent($event, new DateTimeZone(config('app.timezone')))
                    ->diffForHumans(),
                'created_at' => $created_at,
            ];
        });

        // Filter the events based on the selected country
        if ($this->selectedcountry !== 'ALL') {
            $events = $events->filter(function ($event) {
                return str_contains($event['command'], $this->selectedcountry);
            });
        }

        return view('pulse-schedule::livewire.schedule', [
            'events' => $events,
        ]);
    }

    private function getClosureLocation(CallbackEvent $event): string
    {
        $callback = (new ReflectionClass($event))->getProperty('callback')->getValue($event);

        if ($callback instanceof Closure) {
            $function = new ReflectionFunction($callback);

            return sprintf(
                '%s:%s',
                str_replace(app()->basePath().DIRECTORY_SEPARATOR, '', $function->getFileName() ?: ''),
                $function->getStartLine()
            );
        }

        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback)) {
            $className = is_string($callback[0]) ? $callback[0] : $callback[0]::class;

            return sprintf('%s::%s', $className, $callback[1]);
        }

        return sprintf('%s::__invoke', $callback::class); // @phpstan-ignore-line
    }

    private function getCommand(Event $event): string
    {
        $command = str_replace([Application::phpBinary(), Application::artisanBinary()], [
            'php',
            preg_replace("#['\"]#", '', Application::artisanBinary()),
        ], $event->command ?? '');

        if ($event instanceof CallbackEvent) {
            $command = $event->getSummaryForDisplay();

            if (in_array($command, ['Closure', 'Callback'], true)) {
                $command = 'Closure at: '.$this->getClosureLocation($event);
            }
        }

        return mb_strlen($command) > 1 ? "{$command} " : '';
    }

    private function getExpression(Event $event): string
    {
        if (! $event->isRepeatable()) {
            return $event->getExpression();
        }

        return "{$event->getExpression()} ({$event->repeatSeconds}s)";
    }

    private function getNextDueDateForEvent(Event $event, DateTimeZone $timezone): Carbon
    {
        $nextDueDate = Carbon::instance(
            (new CronExpression($event->expression))
                ->getNextRunDate(Carbon::now()->setTimezone($event->timezone))
                ->setTimezone($timezone)
        );

        if (! $event->isRepeatable()) {
            return $nextDueDate;
        }

        $previousDueDate = Carbon::instance(
            (new CronExpression($event->expression))
                ->getPreviousRunDate(Carbon::now()->setTimezone($event->timezone), allowCurrentDate: true)
                ->setTimezone($timezone)
        );

        $now = Carbon::now()->setTimezone($event->timezone);

        if (! $now->copy()->startOfMinute()->eq($previousDueDate)) {
            return $nextDueDate;
        }

        return $now
            ->endOfSecond()
            ->ceilSeconds($event->repeatSeconds); // @phpstan-ignore-line
    }
}
