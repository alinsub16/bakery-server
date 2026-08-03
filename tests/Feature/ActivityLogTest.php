<?php

use App\Models\ActivityLog;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->withRole('admin')->create();
    $this->manager = User::factory()->withRole('manager')->create();
    $this->baker = User::factory()->withRole('baker')->create();
});

it('allows an admin to view activity logs', function () {
    ActivityLog::record($this->admin, 'production.corrected', 'DailyProduction', 1, ['old' => 100, 'new' => 50]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/activity-logs');

    $response->assertStatus(200)->assertJsonCount(1, 'data');
});

it('allows a manager to view activity logs', function () {
    ActivityLog::record($this->admin, 'inventory.corrected', 'DailyInventory', 1, []);

    $response = $this->actingAs($this->manager)->getJson('/api/v1/activity-logs');

    $response->assertStatus(200);
});

it('denies a baker from viewing activity logs', function () {
    $response = $this->actingAs($this->baker)->getJson('/api/v1/activity-logs');

    $response->assertStatus(403);
});

it('filters logs by subject_type', function () {
    ActivityLog::record($this->admin, 'production.corrected', 'DailyProduction', 1, []);
    ActivityLog::record($this->admin, 'inventory.corrected', 'DailyInventory', 2, []);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/activity-logs?subject_type=DailyProduction');

    $response->assertStatus(200)->assertJsonCount(1, 'data');
});

it('filters logs by user_id', function () {
    ActivityLog::record($this->admin, 'production.corrected', 'DailyProduction', 1, []);
    ActivityLog::record($this->manager, 'production.corrected', 'DailyProduction', 2, []);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/activity-logs?user_id={$this->manager->id}");

    $response->assertStatus(200)->assertJsonCount(1, 'data');
});