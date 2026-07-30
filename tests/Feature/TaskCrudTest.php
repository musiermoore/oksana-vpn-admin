<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_update_and_delete_tasks(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $task = Task::query()->create([
            'title' => 'Подготовить отчет по серверам',
            'description' => 'Собрать расходы и доходы',
            'status' => Task::STATUS_IN_PROGRESS,
            'due_date' => '2026-08-05',
        ]);

        $this->actingAs($admin)
            ->get('/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('stats.total', 1)
                ->where('stats.in_progress', 1)
                ->where('tasks.0.title', 'Подготовить отчет по серверам')
            );

        $this->actingAs($admin)
            ->post('/tasks', [
                'title' => 'Добавить фильтр по статусу',
                'description' => 'Для удобства работы со списком',
                'status' => Task::STATUS_TODO,
                'due_date' => '2026-08-10',
            ])
            ->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Добавить фильтр по статусу',
            'status' => Task::STATUS_TODO,
            'due_date' => '2026-08-10',
        ]);

        $this->actingAs($admin)
            ->put("/tasks/{$task->id}", [
                'title' => 'Подготовить финальный отчет по серверам',
                'description' => 'Собрать расходы, доходы и налоги',
                'status' => Task::STATUS_DONE,
                'due_date' => '2026-08-06',
            ])
            ->assertRedirect('/tasks');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Подготовить финальный отчет по серверам',
            'status' => Task::STATUS_DONE,
            'due_date' => '2026-08-06',
        ]);

        $this->actingAs($admin)
            ->delete("/tasks/{$task->id}")
            ->assertRedirect('/tasks');

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }
}
