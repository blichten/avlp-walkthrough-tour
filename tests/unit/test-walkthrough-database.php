<?php
/**
 * Unit tests for AVLP Walkthrough Tour database functionality
 */

class TestWalkthroughDatabase extends WP_UnitTestCase {
    
    private $tour_id;
    private $step_id;
    
    public function setUp(): void {
        parent::setUp();
        
        // Ensure database tables exist
        vlp_walkthrough_create_tables();
    }
    
    public function test_create_tour() {
        $tour_data = [
            'tour_name' => 'Test Tour',
            'tour_description' => 'Test Description',
            'tour_trigger_type' => 'automatic',
            'tour_trigger_value' => '',
            'is_active' => 1
        ];
        
        $tour_id = vlp_walkthrough_create_tour($tour_data);
        
        $this->assertIsInt($tour_id);
        $this->assertGreaterThan(0, $tour_id);
        
        $tour = vlp_walkthrough_get_tour($tour_id);
        $this->assertEquals('Test Tour', $tour->tour_name);
        $this->assertEquals('Test Description', $tour->tour_description);
        $this->assertEquals('automatic', $tour->tour_trigger_type);
        $this->assertEquals(1, $tour->is_active);
        
        $this->tour_id = $tour_id;
    }
    
    public function test_create_tour_step() {
        // Create tour first
        $this->test_create_tour();
        
        $step_data = [
            'tour_id' => $this->tour_id,
            'step_order' => 1,
            'step_title' => 'Test Step',
            'step_content' => 'Test Content',
            'target_selector' => '#test-element',
            'step_position' => 'auto',
            'page_url_pattern' => '',
            'step_delay' => 0,
            'is_active' => 1
        ];
        
        $step_id = vlp_walkthrough_create_tour_step($step_data);
        
        $this->assertIsInt($step_id);
        $this->assertGreaterThan(0, $step_id);
        
        $step = vlp_walkthrough_get_tour_step($step_id);
        $this->assertEquals($this->tour_id, $step->tour_id);
        $this->assertEquals('Test Step', $step->step_title);
        $this->assertEquals('Test Content', $step->step_content);
        $this->assertEquals('#test-element', $step->target_selector);
        
        $this->step_id = $step_id;
    }
    
    public function test_get_tour_steps() {
        // Create tour and steps
        $this->test_create_tour_step();
        
        $steps = vlp_walkthrough_get_tour_steps($this->tour_id);
        
        $this->assertIsArray($steps);
        $this->assertCount(1, $steps);
        $this->assertEquals($this->step_id, $steps[0]->step_id);
    }
    
    public function test_update_tour() {
        // Create tour first
        $this->test_create_tour();
        
        $update_data = [
            'tour_name' => 'Updated Tour Name',
            'tour_description' => 'Updated Description',
            'is_active' => 0
        ];
        
        $result = vlp_walkthrough_update_tour($this->tour_id, $update_data);
        
        $this->assertTrue($result);
        
        $tour = vlp_walkthrough_get_tour($this->tour_id);
        $this->assertEquals('Updated Tour Name', $tour->tour_name);
        $this->assertEquals('Updated Description', $tour->tour_description);
        $this->assertEquals(0, $tour->is_active);
    }
    
    public function test_update_tour_step() {
        // Create tour and step first
        $this->test_create_tour_step();
        
        $update_data = [
            'step_title' => 'Updated Step Title',
            'step_content' => 'Updated Content',
            'step_order' => 2
        ];
        
        $result = vlp_walkthrough_update_tour_step($this->step_id, $update_data);
        
        $this->assertTrue($result);
        
        $step = vlp_walkthrough_get_tour_step($this->step_id);
        $this->assertEquals('Updated Step Title', $step->step_title);
        $this->assertEquals('Updated Content', $step->step_content);
        $this->assertEquals(2, $step->step_order);
    }
    
    public function test_delete_tour() {
        // Create tour first
        $this->test_create_tour();
        
        $result = vlp_walkthrough_delete_tour($this->tour_id);
        
        $this->assertTrue($result);
        
        $tour = vlp_walkthrough_get_tour($this->tour_id);
        $this->assertNull($tour);
    }
    
    public function test_delete_tour_step() {
        // Create tour and step first
        $this->test_create_tour_step();
        
        $result = vlp_walkthrough_delete_tour_step($this->step_id);
        
        $this->assertTrue($result);
        
        $step = vlp_walkthrough_get_tour_step($this->step_id);
        $this->assertNull($step);
    }
    
    public function test_track_user_interaction() {
        // Create tour first
        $this->test_create_tour();
        
        $user_id = $this->factory->user->create();
        
        $result = vlp_walkthrough_track_user_interaction(
            $user_id,
            $this->tour_id,
            'in_progress',
            '/test-page',
            1
        );
        
        $this->assertTrue($result);
        
        $tracking = vlp_walkthrough_get_user_tracking(
            $user_id,
            $this->tour_id,
            '/test-page'
        );
        
        $this->assertNotNull($tracking);
        $this->assertEquals('in_progress', $tracking->status);
        $this->assertEquals(1, $tracking->last_step_completed);
    }
    
    public function test_get_active_tours() {
        // Create multiple tours
        $tour1_id = vlp_walkthrough_create_tour([
            'tour_name' => 'Active Tour 1',
            'tour_description' => 'Description 1',
            'tour_trigger_type' => 'automatic',
            'is_active' => 1
        ]);
        
        $tour2_id = vlp_walkthrough_create_tour([
            'tour_name' => 'Inactive Tour',
            'tour_description' => 'Description 2',
            'tour_trigger_type' => 'automatic',
            'is_active' => 0
        ]);
        
        $tour3_id = vlp_walkthrough_create_tour([
            'tour_name' => 'Active Tour 2',
            'tour_description' => 'Description 3',
            'tour_trigger_type' => 'manual',
            'is_active' => 1
        ]);
        
        $active_tours = vlp_walkthrough_get_active_tours();
        
        $this->assertIsArray($active_tours);
        $this->assertCount(2, $active_tours);
        
        $tour_names = array_column($active_tours, 'tour_name');
        $this->assertContains('Active Tour 1', $tour_names);
        $this->assertContains('Active Tour 2', $tour_names);
        $this->assertNotContains('Inactive Tour', $tour_names);
    }
    
    public function test_get_tour_for_page() {
        // Create tour with steps
        $this->test_create_tour();
        
        // Add step with page pattern
        vlp_walkthrough_create_tour_step([
            'tour_id' => $this->tour_id,
            'step_order' => 1,
            'step_title' => 'Step 1',
            'step_content' => 'Content 1',
            'target_selector' => '#element1',
            'page_url_pattern' => '/test-page'
        ]);
        
        // Add step without page pattern
        vlp_walkthrough_create_tour_step([
            'tour_id' => $this->tour_id,
            'step_order' => 2,
            'step_title' => 'Step 2',
            'step_content' => 'Content 2',
            'target_selector' => '#element2',
            'page_url_pattern' => ''
        ]);
        
        $tour_data = vlp_walkthrough_get_tour_for_page($this->tour_id, '/test-page');
        
        $this->assertNotNull($tour_data);
        $this->assertEquals($this->tour_id, $tour_data->tour_id);
        $this->assertIsArray($tour_data->steps);
        $this->assertCount(2, $tour_data->steps); // Both steps should be returned
    }
    
    public function test_get_tour_stats() {
        // Create tour and track interactions
        $this->test_create_tour();
        
        $user1_id = $this->factory->user->create();
        $user2_id = $this->factory->user->create();
        
        // Track different interactions
        vlp_walkthrough_track_user_interaction($user1_id, $this->tour_id, 'completed', '/test-page', 3);
        vlp_walkthrough_track_user_interaction($user2_id, $this->tour_id, 'skipped_session', '/test-page', 1);
        
        $stats = vlp_walkthrough_get_tour_stats($this->tour_id);
        
        $this->assertIsArray($stats);
        
        $completed_stat = array_filter($stats, function($stat) {
            return $stat->status === 'completed';
        });
        
        $skipped_stat = array_filter($stats, function($stat) {
            return $stat->status === 'skipped_session';
        });
        
        $this->assertCount(1, $completed_stat);
        $this->assertCount(1, $skipped_stat);
        
        $this->assertEquals(1, reset($completed_stat)->count);
        $this->assertEquals(1, reset($completed_stat)->unique_users);
        $this->assertEquals(1, reset($skipped_stat)->count);
        $this->assertEquals(1, reset($skipped_stat)->unique_users);
    }
    
    public function test_user_tour_preferences() {
        $user_id = $this->factory->user->create();
        
        // Test setting disabled preference
        vlp_walkthrough_set_user_tour_preference($user_id, true);
        $this->assertTrue(vlp_walkthrough_user_has_disabled_tours($user_id));
        
        // Test removing disabled preference
        vlp_walkthrough_set_user_tour_preference($user_id, false);
        $this->assertFalse(vlp_walkthrough_user_has_disabled_tours($user_id));
    }
    
    public function tearDown(): void {
        // Clean up test data
        if ($this->step_id) {
            vlp_walkthrough_delete_tour_step($this->step_id);
        }
        
        if ($this->tour_id) {
            vlp_walkthrough_delete_tour($this->tour_id);
        }
        
        parent::tearDown();
    }
}
