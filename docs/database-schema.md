# ForgeDesk Database Schema

Generated 2026-10-12 from the live PostgreSQL 16 databases (read-only catalog queries).

## Main database: forgedesk

105 tables.

### asset_machine

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| asset_id | bigint | NO |  |
| machine_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (asset_id, machine_id)
- PRIMARY KEY (id)
- FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
- FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE

**Indexes**

- asset_machine_asset_id_machine_id_unique (unique) (asset_id, machine_id)
- asset_machine_asset_id_index (asset_id)
- asset_machine_machine_id_index (machine_id)

### assets

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| description | text | yes |  |
| documents | json | yes |  |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)

**Indexes**

- assets_name_index (name)

### backup_runs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| run_date | date | NO |  |
| started_at | timestamp(0) without time zone | NO |  |
| finished_at | timestamp(0) without time zone | yes |  |
| status | character varying(255) | NO |  |
| components | json | yes |  |
| error_message | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)

**Indexes**

- backup_runs_run_date_index (run_date)

### business_jobs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| job_number | character varying(100) | NO |  |
| job_name | character varying(255) | NO |  |
| customer_name | character varying(255) | yes |  |
| site_address | character varying(500) | yes |  |
| contact_name | character varying(255) | yes |  |
| contact_phone | character varying(50) | yes |  |
| contact_email | character varying(255) | yes |  |
| status | character varying(255) | NO | 'active' |
| start_date | date | yes |  |
| target_completion_date | date | yes |  |
| actual_completion_date | date | yes |  |
| notes | text | yes |  |
| created_by_id | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| project_manager | character varying(255) | yes |  |
| project_manager_id | bigint | yes |  |
| superintendent | character varying(255) | yes |  |
| superintendent_id | bigint | yes |  |

**Constraints**

- UNIQUE (job_number)
- PRIMARY KEY (id)
- FOREIGN KEY (created_by_id) REFERENCES users(id)
- FOREIGN KEY (project_manager_id) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (superintendent_id) REFERENCES users(id) ON DELETE SET NULL
- CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('on_hold'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text])))

**Indexes**

- business_jobs_job_number_unique (unique) (job_number)
- business_jobs_customer_name_index (customer_name)
- business_jobs_job_number_index (job_number)
- business_jobs_status_index (status)
- business_jobs_target_completion_date_index (target_completion_date)

### cache

| Column | Type | Null | Default |
|---|---|---|---|
| key | character varying(255) | NO |  |
| value | text | NO |  |
| expiration | integer | NO |  |

**Constraints**

- PRIMARY KEY (key)

### cache_locks

| Column | Type | Null | Default |
|---|---|---|---|
| key | character varying(255) | NO |  |
| owner | character varying(255) | NO |  |
| expiration | integer | NO |  |

**Constraints**

- PRIMARY KEY (key)

### categories

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| code | character varying(255) | yes |  |
| parent_id | bigint | yes |  |
| description | text | yes |  |
| system | character varying(255) | yes |  |
| sort_order | integer | NO | 0 |
| is_active | boolean | NO | true |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (code)
- PRIMARY KEY (id)
- FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE

**Indexes**

- categories_code_unique (unique) (code)
- categories_code_index (code)
- categories_is_active_index (is_active)
- categories_name_index (name)
- categories_parent_id_index (parent_id)
- categories_system_index (system)

### category_product

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| product_id | bigint | NO |  |
| category_id | bigint | NO |  |
| is_primary | boolean | NO | false |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (product_id, category_id)
- PRIMARY KEY (id)
- FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE

**Indexes**

- category_product_product_id_category_id_unique (unique) (product_id, category_id)
- category_product_category_id_index (category_id)
- category_product_is_primary_index (is_primary)
- category_product_product_id_index (product_id)

### committed_inventory

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| product_id | bigint | NO |  |
| order_id | bigint | NO |  |
| order_item_id | bigint | NO |  |
| quantity_committed | integer | NO |  |
| committed_date | date | NO |  |
| expected_release_date | date | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
- FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE

**Indexes**

- committed_inventory_order_id_index (order_id)
- committed_inventory_product_id_index (product_id)

### company_locations

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| is_primary | boolean | NO | false |
| address_line1 | character varying(255) | yes |  |
| address_line2 | character varying(255) | yes |  |
| city | character varying(255) | yes |  |
| state | character varying(255) | yes |  |
| zip | character varying(255) | yes |  |
| country | character varying(255) | NO | 'USA' |
| phone | character varying(255) | yes |  |
| fax | character varying(255) | yes |  |
| email | character varying(255) | yes |  |
| notes | text | yes |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)

**Indexes**

- company_locations_is_primary_index (is_primary)

### company_settings

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| logo_path | character varying(255) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| dashboard_default_layout | json | yes |  |

**Constraints**

- PRIMARY KEY (id)

### configurator_door_types

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| series | character varying(20) | NO |  |
| stile_name | character varying(50) | NO |  |
| stile_height | numeric(6,4) | NO |  |
| bev_pn | character varying(20) | yes |  |
| rab_pn | character varying(20) | yes |  |
| cp_pn | character varying(20) | yes |  |
| ast_pn | character varying(20) | yes |  |
| inact_pn | character varying(20) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (series, stile_name)
- PRIMARY KEY (id)

**Indexes**

- configurator_door_types_series_stile_name_unique (unique) (series, stile_name)

### configurator_frame_components

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| frame_profile_id | bigint | NO |  |
| label | character varying(100) | NO |  |
| product_id | bigint | NO |  |
| qty_type | character varying(255) | NO | 'per_opening' |
| qty_per | numeric(8,3) | NO | '1' |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| glass_thicknesses | json | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (frame_profile_id) REFERENCES configurator_frame_profiles(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id)
- CHECK (((qty_type)::text = ANY (ARRAY[('per_opening'::character varying)::text, ('per_door'::character varying)::text, ('per_length'::character varying)::text])))

**Indexes**

- configurator_frame_components_frame_profile_id_index (frame_profile_id)

### configurator_frame_fasteners

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| frame_component_id | bigint | NO |  |
| label | character varying(100) | NO |  |
| product_id | bigint | NO |  |
| qty_per | numeric(8,3) | NO | '1' |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (frame_component_id) REFERENCES configurator_frame_components(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id)

**Indexes**

- configurator_frame_fasteners_frame_component_id_index (frame_component_id)

### configurator_frame_profiles

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| frame_series_id | bigint | NO |  |
| role_label | character varying(100) | NO |  |
| product_id | bigint | NO |  |
| formula | json | NO |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| condition | character varying(20) | yes |  |
| glass_thicknesses | json | yes |  |
| section_height | numeric(8,4) | NO | '0' |
| qty_per_opening | integer | NO | 1 |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (frame_series_id) REFERENCES configurator_frame_series(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id)

**Indexes**

- configurator_frame_profiles_frame_series_id_index (frame_series_id)

### configurator_frame_series

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| frame_system_id | bigint | NO |  |
| name | character varying(100) | NO |  |
| code | character varying(30) | NO |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (frame_system_id, code)
- PRIMARY KEY (id)
- FOREIGN KEY (frame_system_id) REFERENCES configurator_frame_systems(id) ON DELETE CASCADE

**Indexes**

- configurator_frame_series_frame_system_id_code_unique (unique) (frame_system_id, code)

### configurator_frame_systems

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(100) | NO |  |
| code | character varying(30) | NO |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (code)
- PRIMARY KEY (id)

**Indexes**

- configurator_frame_systems_code_unique (unique) (code)

### configurator_glass_specs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| thickness | character varying(10) | NO |  |
| stop_pn | character varying(20) | yes |  |
| gasket_pn | character varying(20) | yes |  |
| gasket2_pn | character varying(20) | yes |  |
| gasket_qty_factor | numeric(4,2) | yes |  |
| stop_height | numeric(4,2) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (thickness)
- PRIMARY KEY (id)

**Indexes**

- configurator_glass_specs_thickness_unique (unique) (thickness)

### configurator_hinge_spacing_standards

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(100) | NO |  |
| top_distance | numeric(8,4) | NO |  |
| top_label | character varying(50) | NO | 'top of prep' |
| bottom_distance | numeric(8,4) | NO |  |
| bottom_reference | character varying(255) | NO | 'door_bottom' |
| bottom_label | character varying(50) | NO | 'bottom of prep' |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (name)
- PRIMARY KEY (id)
- CHECK (((bottom_reference)::text = ANY (ARRAY[('door_bottom'::character varying)::text, ('floor'::character varying)::text])))

**Indexes**

- configurator_hinge_spacing_standards_name_unique (unique) (name)

### configurator_hwlib_backer_fasteners

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| backer_id | bigint | NO |  |
| fastener_id | bigint | NO |  |
| qty | numeric(8,3) | NO | '1' |
| notes | text | yes |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (backer_id, fastener_id)
- PRIMARY KEY (id)
- FOREIGN KEY (backer_id) REFERENCES configurator_hwlib_backers(id) ON DELETE CASCADE
- FOREIGN KEY (fastener_id) REFERENCES configurator_hwlib_fasteners(id) ON DELETE RESTRICT

**Indexes**

- configurator_hwlib_backer_fasteners_backer_id_fastener_id_uniqu (unique) (backer_id, fastener_id)

### configurator_hwlib_backers

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| pn | character varying(255) | NO |  |
| description | character varying(255) | yes |  |
| notes | text | yes |  |
| active | boolean | NO | true |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| imported_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (pn)
- PRIMARY KEY (id)

**Indexes**

- configurator_hwlib_backers_pn_unique (unique) (pn)

### configurator_hwlib_categories

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| description | text | yes |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| imported_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (name)
- PRIMARY KEY (id)

**Indexes**

- configurator_hwlib_categories_name_unique (unique) (name)

### configurator_hwlib_category_variables

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| category_id | bigint | NO |  |
| variable_id | bigint | NO |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (category_id, variable_id)
- PRIMARY KEY (id)
- FOREIGN KEY (category_id) REFERENCES configurator_hwlib_categories(id) ON DELETE CASCADE
- FOREIGN KEY (variable_id) REFERENCES configurator_hwlib_variables(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_category_variables_category_id_variable_id_u (unique) (category_id, variable_id)

### configurator_hwlib_fasteners

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| pn | character varying(255) | NO |  |
| description | character varying(255) | yes |  |
| notes | text | yes |  |
| active | boolean | NO | true |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| imported_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (pn)
- PRIMARY KEY (id)

**Indexes**

- configurator_hwlib_fasteners_pn_unique (unique) (pn)

### configurator_hwlib_functions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| code | character varying(50) | NO |  |
| label | character varying(255) | NO |  |
| group_name | character varying(255) | yes |  |
| notes | text | yes |  |
| sort_order | integer | NO | 0 |
| active | boolean | NO | true |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (code)
- PRIMARY KEY (id)

**Indexes**

- configurator_hwlib_functions_code_unique (unique) (code)

### configurator_hwlib_item_backers

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| item_id | bigint | NO |  |
| side | character varying(255) | NO |  |
| series | character varying(255) | NO |  |
| pn | character varying(255) | yes |  |
| description | character varying(255) | yes |  |
| qty | numeric(8,3) | NO | '1' |
| notes | text | yes |  |
| sort_order | integer | NO | 0 |
| backer_id | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (backer_id) REFERENCES configurator_hwlib_backers(id) ON DELETE RESTRICT
- FOREIGN KEY (item_id) REFERENCES configurator_hwlib_items(id) ON DELETE CASCADE
- CHECK (((series)::text = ANY (ARRAY[('Standard'::character varying)::text, ('Thermal'::character varying)::text, ('Monumental'::character varying)::text])))
- CHECK (((side)::text = ANY (ARRAY[('door'::character varying)::text, ('frame'::character varying)::text])))

**Indexes**

- configurator_hwlib_item_backers_item_id_index (item_id)

### configurator_hwlib_item_functions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| item_id | bigint | NO |  |
| function_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (item_id, function_id)
- PRIMARY KEY (id)
- FOREIGN KEY (function_id) REFERENCES configurator_hwlib_functions(id) ON DELETE RESTRICT
- FOREIGN KEY (item_id) REFERENCES configurator_hwlib_items(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_item_functions_item_id_function_id_unique (unique) (item_id, function_id)

### configurator_hwlib_item_values

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| item_id | bigint | NO |  |
| variable_id | bigint | NO |  |
| value_text | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (item_id, variable_id)
- PRIMARY KEY (id)
- FOREIGN KEY (item_id) REFERENCES configurator_hwlib_items(id) ON DELETE CASCADE
- FOREIGN KEY (variable_id) REFERENCES configurator_hwlib_variables(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_item_values_item_id_variable_id_unique (unique) (item_id, variable_id)

### configurator_hwlib_items

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| category_id | bigint | NO |  |
| name | character varying(255) | NO |  |
| manufacturer | character varying(255) | yes |  |
| model_number | character varying(255) | yes |  |
| pn | character varying(255) | yes |  |
| notes | text | yes |  |
| active | boolean | NO | true |
| vos_standard | boolean | NO | false |
| finishes | json | NO | '[]' |
| min_width | numeric(8,4) | yes |  |
| max_width | numeric(8,4) | yes |  |
| min_height | numeric(8,4) | yes |  |
| max_height | numeric(8,4) | yes |  |
| field_install | boolean | NO | false |
| handed | boolean | NO | false |
| default_strike_item_id | bigint | yes |  |
| default_cover_item_id | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| subcategory_id | bigint | yes |  |
| needs_review | boolean | NO | false |
| imported_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (category_id) REFERENCES configurator_hwlib_categories(id) ON DELETE RESTRICT
- FOREIGN KEY (default_cover_item_id) REFERENCES configurator_hwlib_items(id) ON DELETE SET NULL
- FOREIGN KEY (default_strike_item_id) REFERENCES configurator_hwlib_items(id) ON DELETE SET NULL
- FOREIGN KEY (subcategory_id) REFERENCES configurator_hwlib_subcategories(id) ON DELETE SET NULL

**Indexes**

- configurator_hwlib_items_category_id_index (category_id)

### configurator_hwlib_link_functions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| link_id | bigint | NO |  |
| function_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (link_id, function_id)
- PRIMARY KEY (id)
- FOREIGN KEY (function_id) REFERENCES configurator_hwlib_functions(id) ON DELETE RESTRICT
- FOREIGN KEY (link_id) REFERENCES configurator_hwlib_links(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_link_functions_link_id_function_id_unique (unique) (link_id, function_id)

### configurator_hwlib_link_values

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| link_id | bigint | NO |  |
| variable_id | bigint | NO |  |
| value_text | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (link_id, variable_id)
- PRIMARY KEY (id)
- FOREIGN KEY (link_id) REFERENCES configurator_hwlib_links(id) ON DELETE CASCADE
- FOREIGN KEY (variable_id) REFERENCES configurator_hwlib_variables(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_link_values_link_id_variable_id_unique (unique) (link_id, variable_id)

### configurator_hwlib_links

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| configuration_id | bigint | NO |  |
| item_id | bigint | NO |  |
| quantity | integer | NO | 1 |
| notes | text | yes |  |
| series | character varying(255) | NO | 'Standard' |
| leaf | character varying(255) | NO | 'both' |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| source_set_id | bigint | yes |  |

**Constraints**

- UNIQUE (configuration_id, item_id, leaf)
- PRIMARY KEY (id)
- FOREIGN KEY (configuration_id) REFERENCES door_frame_configurations(id) ON DELETE CASCADE
- FOREIGN KEY (item_id) REFERENCES configurator_hwlib_items(id) ON DELETE RESTRICT
- FOREIGN KEY (source_set_id) REFERENCES configurator_hwlib_sets(id) ON DELETE SET NULL
- CHECK (((leaf)::text = ANY (ARRAY[('both'::character varying)::text, ('active'::character varying)::text, ('inactive'::character varying)::text])))
- CHECK (((series)::text = ANY (ARRAY[('Standard'::character varying)::text, ('Thermal'::character varying)::text, ('Monumental'::character varying)::text])))

**Indexes**

- configurator_hwlib_links_configuration_id_item_id_leaf_unique (unique) (configuration_id, item_id, leaf)

### configurator_hwlib_set_item_functions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| set_item_id | bigint | NO |  |
| function_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (set_item_id, function_id)
- PRIMARY KEY (id)
- FOREIGN KEY (function_id) REFERENCES configurator_hwlib_functions(id) ON DELETE RESTRICT
- FOREIGN KEY (set_item_id) REFERENCES configurator_hwlib_set_items(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_set_item_functions_set_item_id_function_id_u (unique) (set_item_id, function_id)

### configurator_hwlib_set_item_values

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| set_item_id | bigint | NO |  |
| variable_id | bigint | NO |  |
| value_text | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (set_item_id, variable_id)
- PRIMARY KEY (id)
- FOREIGN KEY (set_item_id) REFERENCES configurator_hwlib_set_items(id) ON DELETE CASCADE
- FOREIGN KEY (variable_id) REFERENCES configurator_hwlib_variables(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_set_item_values_set_item_id_variable_id_uniq (unique) (set_item_id, variable_id)

### configurator_hwlib_set_items

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| set_id | bigint | NO |  |
| item_id | bigint | NO |  |
| quantity | integer | NO | 1 |
| notes | text | yes |  |
| series | character varying(255) | NO | 'Standard' |
| leaf | character varying(255) | NO | 'both' |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (set_id, item_id, leaf)
- PRIMARY KEY (id)
- FOREIGN KEY (item_id) REFERENCES configurator_hwlib_items(id) ON DELETE RESTRICT
- FOREIGN KEY (set_id) REFERENCES configurator_hwlib_sets(id) ON DELETE CASCADE
- CHECK (((leaf)::text = ANY (ARRAY[('both'::character varying)::text, ('active'::character varying)::text, ('inactive'::character varying)::text])))
- CHECK (((series)::text = ANY (ARRAY[('Standard'::character varying)::text, ('Thermal'::character varying)::text, ('Monumental'::character varying)::text])))

**Indexes**

- configurator_hwlib_set_items_set_id_item_id_leaf_unique (unique) (set_id, item_id, leaf)

### configurator_hwlib_sets

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(120) | NO |  |
| notes | text | yes |  |
| is_pair | boolean | NO | false |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| business_job_id | bigint | NO |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (business_job_id) REFERENCES business_jobs(id) ON DELETE CASCADE

### configurator_hwlib_subcategories

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| category_id | bigint | NO |  |
| name | character varying(255) | NO |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (category_id, name)
- PRIMARY KEY (id)
- FOREIGN KEY (category_id) REFERENCES configurator_hwlib_categories(id) ON DELETE CASCADE

**Indexes**

- configurator_hwlib_subcategories_category_id_name_unique (unique) (category_id, name)

### configurator_hwlib_variables

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| code | character varying(50) | NO |  |
| label | character varying(255) | NO |  |
| group_name | character varying(255) | NO |  |
| var_type | character varying(255) | NO |  |
| unit | character varying(20) | yes |  |
| options | json | NO | '[]' |
| is_calculated | boolean | NO | false |
| formula | text | yes |  |
| default_value | character varying(255) | yes |  |
| notes | text | yes |  |
| sort_order | integer | NO | 0 |
| overrides_variable_id | bigint | yes |  |
| side | character varying(255) | yes |  |
| show_in_report | boolean | NO | true |
| is_inspection | boolean | NO | false |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (code)
- PRIMARY KEY (id)
- FOREIGN KEY (overrides_variable_id) REFERENCES configurator_hwlib_variables(id) ON DELETE SET NULL
- CHECK (((side)::text = ANY (ARRAY[('door'::character varying)::text, ('frame'::character varying)::text])))
- CHECK (((var_type)::text = ANY (ARRAY[('number'::character varying)::text, ('text'::character varying)::text, ('boolean'::character varying)::text, ('select'::character varying)::text, ('degree_matrix'::character varying)::text])))

**Indexes**

- configurator_hwlib_variables_code_unique (unique) (code)

### configurator_mid_lugs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| rail_pn | character varying(20) | NO |  |
| lug_pn | character varying(30) | NO |  |
| f1_pn | character varying(20) | yes |  |
| f1_qty | numeric(5,2) | NO | '0' |
| f2_pn | character varying(20) | yes |  |
| f2_qty | numeric(5,2) | NO | '0' |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (rail_pn)
- PRIMARY KEY (id)

**Indexes**

- configurator_mid_lugs_rail_pn_unique (unique) (rail_pn)

### configurator_pdf_templates

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| report_type | character varying(20) | NO |  |
| layout | json | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (report_type)
- PRIMARY KEY (id)

**Indexes**

- configurator_pdf_templates_report_type_unique (unique) (report_type)

### configurator_rail_lugs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| rail_pn | character varying(20) | NO |  |
| lug_pn | character varying(30) | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (rail_pn)
- PRIMARY KEY (id)

**Indexes**

- configurator_rail_lugs_rail_pn_unique (unique) (rail_pn)

### configurator_rails

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| rail_type | character varying(255) | NO |  |
| label | character varying(40) | NO |  |
| std_pn | character varying(20) | yes |  |
| thermal_pn | character varying(20) | yes |  |
| mon_pn | character varying(20) | yes |  |
| value_in | numeric(8,5) | NO |  |
| stacked_std_pn | character varying(20) | yes |  |
| stacked_thermal_pn | character varying(20) | yes |  |
| stacked_mon_pn | character varying(20) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (rail_type, label)
- PRIMARY KEY (id)
- CHECK (((rail_type)::text = ANY (ARRAY[('top'::character varying)::text, ('bot'::character varying)::text, ('mid'::character varying)::text])))

**Indexes**

- configurator_rails_rail_type_label_unique (unique) (rail_type, label)

### configurator_setting_block_kits

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| series | character varying(20) | NO |  |
| glass_thickness | character varying(10) | NO |  |
| kit1_pn | character varying(20) | yes |  |
| kit2_pn | character varying(20) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (series, glass_thickness)
- PRIMARY KEY (id)

**Indexes**

- configurator_setting_block_kits_series_glass_thickness_unique (unique) (series, glass_thickness)

### configurator_settings

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| top_gap | numeric(8,4) | NO | 0.125 |
| bottom_gap | numeric(8,4) | NO | 0.6875 |
| hinge_gap | numeric(8,4) | NO | 0.0625 |
| lock_gap | numeric(8,4) | NO | 0.0625 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)

### configurator_tie_rods

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| pn | character varying(20) | NO |  |
| min_len | numeric(8,4) | yes |  |
| max_len | numeric(8,4) | yes |  |
| series | character varying(50) | yes |  |
| mid_val | numeric(8,4) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)

**Indexes**

- configurator_tie_rods_series_min_len_max_len_index (series, min_len, max_len)

### cycle_count_items

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| session_id | bigint | NO |  |
| product_id | bigint | NO |  |
| location_id | bigint | yes |  |
| system_quantity | integer | NO |  |
| counted_quantity | integer | yes |  |
| variance | integer | NO | 0 |
| variance_status | character varying(255) | NO | 'pending' |
| count_notes | text | yes |  |
| counted_by | bigint | yes |  |
| counted_at | timestamp(0) without time zone | yes |  |
| adjustment_created | boolean | NO | false |
| transaction_id | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (counted_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
- FOREIGN KEY (transaction_id) REFERENCES inventory_transactions(id) ON DELETE SET NULL
- CHECK (((variance_status)::text = ANY (ARRAY[('pending'::character varying)::text, ('within_tolerance'::character varying)::text, ('requires_review'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text])))

**Indexes**

- cycle_count_items_product_id_index (product_id)
- cycle_count_items_session_id_index (session_id)
- cycle_count_items_variance_status_index (variance_status)

### cycle_count_sessions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| session_number | character varying(255) | NO |  |
| location | character varying(255) | yes |  |
| category_id | bigint | yes |  |
| status | character varying(255) | NO | 'planned' |
| scheduled_date | date | NO |  |
| started_at | timestamp(0) without time zone | yes |  |
| completed_at | timestamp(0) without time zone | yes |  |
| assigned_to | bigint | yes |  |
| reviewed_by | bigint | yes |  |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| storage_location_ids | json | yes |  |

**Constraints**

- UNIQUE (session_number)
- PRIMARY KEY (id)
- FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
- FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
- CHECK (((status)::text = ANY (ARRAY[('planned'::character varying)::text, ('in_progress'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text])))

**Indexes**

- cycle_count_sessions_session_number_unique (unique) (session_number)

### door_frame_configuration_doors

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| configuration_id | bigint | NO |  |
| door_tag | character varying(50) | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| leaf | character varying(10) | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (configuration_id) REFERENCES door_frame_configurations(id) ON DELETE CASCADE

### door_frame_configuration_hwlib_sets

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| configuration_id | bigint | NO |  |
| set_id | bigint | NO |  |
| applied_at | timestamp(0) without time zone | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (configuration_id, set_id)
- PRIMARY KEY (id)
- FOREIGN KEY (configuration_id) REFERENCES door_frame_configurations(id) ON DELETE CASCADE
- FOREIGN KEY (set_id) REFERENCES configurator_hwlib_sets(id) ON DELETE CASCADE

**Indexes**

- door_frame_configuration_hwlib_sets_configuration_id_set_id_uni (unique) (configuration_id, set_id)

### door_frame_configurations

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| business_job_id | bigint | NO |  |
| job_scope | character varying(255) | NO |  |
| quantity | integer | NO | 1 |
| status | character varying(255) | NO | 'draft' |
| notes | text | yes |  |
| created_by_id | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| work_order_id | bigint | yes |  |
| job_reservation_id | bigint | yes |  |
| duplicate_group_id | uuid | yes |  |
| archived | boolean | NO | false |
| archived_at | timestamp(0) without time zone | yes |  |
| cutflow_include_frame | boolean | NO | true |
| cutflow_include_door | boolean | NO | true |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (business_job_id) REFERENCES business_jobs(id) ON DELETE RESTRICT
- FOREIGN KEY (created_by_id) REFERENCES users(id)
- FOREIGN KEY (job_reservation_id) REFERENCES job_reservations(id) ON DELETE SET NULL
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE SET NULL
- CHECK (((job_scope)::text = ANY (ARRAY[('door_and_frame'::character varying)::text, ('frame_only'::character varying)::text, ('door_only'::character varying)::text])))
- CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'reserved'::character varying, 'cut_released'::character varying, 'released'::character varying, 'in_progress'::character varying, 'completed'::character varying, 'on_hold'::character varying, 'cancelled'::character varying])::text[])))

**Indexes**

- door_frame_configurations_archived_index (archived)
- door_frame_configurations_duplicate_group_id_index (duplicate_group_id)

### door_frame_door_configs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| configuration_id | bigint | NO |  |
| leaf_type | character varying(255) | NO |  |
| preset | character varying(255) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| door_series | character varying(20) | yes |  |
| stile_width | character varying(50) | yes |  |
| handing | character varying(30) | yes |  |
| hinge_type | character varying(30) | yes |  |
| opening_angle | smallint | NO | '90'::smallint |
| bottom_gap | numeric(6,4) | NO | 0.6875 |
| top_rail_label | character varying(40) | yes |  |
| bot_rail_label | character varying(40) | yes |  |
| mid_rail_label | character varying(40) | yes |  |
| mid_qty | smallint | NO | '0'::smallint |
| mid_loc1 | numeric(8,4) | yes |  |
| mid_loc2 | numeric(8,4) | yes |  |
| glazing | character varying(10) | yes |  |
| leaf | character varying(10) | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (configuration_id) REFERENCES door_frame_configurations(id) ON DELETE CASCADE
- CHECK (((leaf_type)::text = ANY (ARRAY[('single'::character varying)::text, ('active'::character varying)::text, ('inactive'::character varying)::text])))
- CHECK (((preset)::text = ANY (ARRAY[('standard'::character varying)::text, ('ws_continuous'::character varying)::text, ('ws_butt'::character varying)::text])))

### door_frame_door_parts

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| door_config_id | bigint | NO |  |
| part_label | character varying(100) | NO |  |
| product_id | bigint | NO |  |
| calculated_length | numeric(10,4) | yes |  |
| is_auto_generated | boolean | NO | false |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| quantity | numeric(10,3) | NO | '1' |
| unit_type | character varying(255) | NO | 'length' |
| source_type | character varying(255) | NO | 'manual' |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (door_config_id) REFERENCES door_frame_door_configs(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id)
- CHECK (((source_type)::text = ANY (ARRAY[('extrusion'::character varying)::text, ('component'::character varying)::text, ('manual'::character varying)::text])))
- CHECK (((unit_type)::text = ANY (ARRAY[('length'::character varying)::text, ('qty'::character varying)::text])))

### door_frame_frame_configs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| configuration_id | bigint | NO |  |
| glazing | character varying(255) | yes |  |
| has_transom | boolean | NO | false |
| transom_glazing | character varying(255) | yes |  |
| total_frame_height | numeric(10,4) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| frame_series_id | bigint | yes |  |
| has_threshold | boolean | NO | false |
| frame_system_product_id | bigint | yes |  |

**Constraints**

- UNIQUE (configuration_id)
- PRIMARY KEY (id)
- FOREIGN KEY (configuration_id) REFERENCES door_frame_configurations(id) ON DELETE CASCADE
- FOREIGN KEY (frame_series_id) REFERENCES configurator_frame_series(id)
- FOREIGN KEY (frame_system_product_id) REFERENCES products(id)
- CHECK (((glazing)::text = ANY (ARRAY[('0.25'::character varying)::text, ('0.5'::character varying)::text, ('1.0'::character varying)::text])))
- CHECK (((transom_glazing)::text = ANY (ARRAY[('0.25'::character varying)::text, ('0.5'::character varying)::text, ('1.0'::character varying)::text])))

**Indexes**

- door_frame_frame_configs_configuration_id_unique (unique) (configuration_id)

### door_frame_frame_parts

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| frame_config_id | bigint | NO |  |
| part_label | character varying(100) | NO |  |
| product_id | bigint | NO |  |
| calculated_length | numeric(10,4) | yes |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| quantity | numeric(10,3) | NO | '1' |
| unit_type | character varying(255) | NO | 'length' |
| source_type | character varying(255) | NO | 'manual' |
| is_auto_generated | boolean | NO | false |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (frame_config_id) REFERENCES door_frame_frame_configs(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id)
- CHECK (((source_type)::text = ANY (ARRAY[('profile'::character varying)::text, ('component'::character varying)::text, ('fastener'::character varying)::text, ('manual'::character varying)::text])))
- CHECK (((unit_type)::text = ANY (ARRAY[('length'::character varying)::text, ('qty'::character varying)::text])))

### door_frame_hardware_parts

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| configuration_id | bigint | NO |  |
| part_label | character varying(150) | NO |  |
| product_id | bigint | yes |  |
| quantity | numeric(10,3) | NO | '1' |
| source_type | character varying(255) | NO | 'manual' |
| is_auto_generated | boolean | NO | false |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| hwlib_link_id | bigint | yes |  |
| manufacturer | character varying(150) | yes |  |
| model_number | character varying(150) | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (configuration_id) REFERENCES door_frame_configurations(id) ON DELETE CASCADE
- FOREIGN KEY (hwlib_link_id) REFERENCES configurator_hwlib_links(id) ON DELETE SET NULL
- FOREIGN KEY (product_id) REFERENCES products(id)
- CHECK (((source_type)::text = ANY (ARRAY[('item'::character varying)::text, ('backer'::character varying)::text, ('fastener'::character varying)::text, ('manual'::character varying)::text])))

**Indexes**

- door_frame_hardware_parts_configuration_id_index (configuration_id)

### door_frame_opening_specs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| configuration_id | bigint | NO |  |
| opening_type | character varying(255) | NO |  |
| hand_single | character varying(255) | yes |  |
| hand_pair | character varying(255) | yes |  |
| door_opening_width | numeric(10,4) | NO |  |
| door_opening_height | numeric(10,4) | NO |  |
| hinging | character varying(255) | NO |  |
| finish | character varying(255) | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| glazing | character varying(255) | yes |  |
| butt_hinge_count | smallint | yes |  |
| hinge_spacing_standard_id | bigint | yes |  |

**Constraints**

- UNIQUE (configuration_id)
- PRIMARY KEY (id)
- FOREIGN KEY (configuration_id) REFERENCES door_frame_configurations(id) ON DELETE CASCADE
- FOREIGN KEY (hinge_spacing_standard_id) REFERENCES configurator_hinge_spacing_standards(id) ON DELETE SET NULL
- CHECK (((finish)::text = ANY (ARRAY[('c2'::character varying)::text, ('db'::character varying)::text, ('bl'::character varying)::text])))
- CHECK (((hand_pair)::text = ANY (ARRAY[('rhr_active'::character varying)::text, ('lhra_active'::character varying)::text])))
- CHECK (((hand_single)::text = ANY (ARRAY[('lh_inswing'::character varying)::text, ('rh_inswing'::character varying)::text, ('lhr'::character varying)::text, ('rhr'::character varying)::text])))
- CHECK (((hinging)::text = ANY (ARRAY[('continuous'::character varying)::text, ('butt'::character varying)::text, ('pivot_offset'::character varying)::text, ('pivot_center'::character varying)::text])))
- CHECK (((opening_type)::text = ANY (ARRAY[('single'::character varying)::text, ('pair'::character varying)::text])))

**Indexes**

- door_frame_opening_specs_configuration_id_unique (unique) (configuration_id)

### fabrication_documents

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| title | character varying(255) | NO |  |
| type | character varying(255) | NO | 'fabrication' |
| hwtype | character varying(255) | yes |  |
| manufacturer | character varying(255) | yes |  |
| file_name | character varying(255) | yes |  |
| file_path | character varying(255) | yes |  |
| file_size | bigint | yes |  |
| file_mime | character varying(255) | yes |  |
| tags | json | yes |  |
| notes | text | yes |  |
| created_by | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
- CHECK (((type)::text = ANY (ARRAY[('fabrication'::character varying)::text, ('installation'::character varying)::text, ('maintenance'::character varying)::text])))

### failed_jobs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| uuid | character varying(255) | NO |  |
| connection | text | NO |  |
| queue | text | NO |  |
| payload | text | NO |  |
| exception | text | NO |  |
| failed_at | timestamp(0) without time zone | NO | CURRENT_TIMESTAMP |

**Constraints**

- UNIQUE (uuid)
- PRIMARY KEY (id)

**Indexes**

- failed_jobs_uuid_unique (unique) (uuid)

### fd_elevation_types

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| color | character varying(7) | NO | '#6c757d' |
| sort_order | integer | NO | 0 |
| active | boolean | NO | true |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| aliases | json | yes |  |
| standard_joint_count | smallint | yes |  |

**Constraints**

- PRIMARY KEY (id)

### fd_job_step_templates

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| sort_order | integer | NO | 0 |
| active | boolean | NO | true |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)

### fd_job_steps

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| sort_order | integer | NO | 0 |
| status | character varying(255) | NO | 'pending' |
| completed_by_id | bigint | yes |  |
| completed_at | timestamp(0) without time zone | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| work_order_id | bigint | NO |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (completed_by_id) REFERENCES fd_users(id) ON DELETE SET NULL
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE CASCADE

### fd_rate_constants

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| key | character varying(255) | NO |  |
| value | numeric(8,4) | NO |  |
| description | character varying(255) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (key)
- PRIMARY KEY (id)

**Indexes**

- fd_rate_constants_key_unique (unique) (key)

### fd_stage_log

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| stage_id | bigint | NO |  |
| user_id | bigint | yes |  |
| message | text | NO |  |
| created_at | timestamp(0) without time zone | NO | CURRENT_TIMESTAMP |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (stage_id) REFERENCES fd_wo_stages(id) ON DELETE CASCADE
- FOREIGN KEY (user_id) REFERENCES fd_users(id) ON DELETE SET NULL

### fd_stage_template_sets

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| elevation_type_id | bigint | NO |  |
| name | character varying(255) | NO |  |
| sort_order | integer | NO | 0 |
| is_default | boolean | NO | false |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| minutes_per_joint | numeric(8,2) | yes |  |

**Constraints**

- UNIQUE (elevation_type_id, name)
- PRIMARY KEY (id)
- FOREIGN KEY (elevation_type_id) REFERENCES fd_elevation_types(id) ON DELETE CASCADE

**Indexes**

- fd_stage_template_sets_elevation_type_id_name_unique (unique) (elevation_type_id, name)

### fd_stage_templates

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| job_type | character varying(255) | yes |  |
| name | character varying(255) | NO |  |
| description | text | yes |  |
| sort_order | integer | NO | 0 |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| elevation_type_id | bigint | yes |  |
| default_user_id | bigint | yes |  |
| blocks_next | boolean | NO | true |
| template_set_id | bigint | yes |  |
| minutes_per_joint | numeric(8,2) | yes |  |
| phase | integer | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (default_user_id) REFERENCES fd_users(id) ON DELETE SET NULL
- FOREIGN KEY (elevation_type_id) REFERENCES fd_elevation_types(id) ON DELETE SET NULL
- FOREIGN KEY (template_set_id) REFERENCES fd_stage_template_sets(id) ON DELETE SET NULL

### fd_users

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| initials | character varying(10) | yes |  |
| role | character varying(255) | NO | 'worker' |
| email | character varying(255) | yes |  |
| active | boolean | NO | true |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| fab_pin | character varying(255) | yes |  |

**Constraints**

- PRIMARY KEY (id)

### fd_wo_assignments

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| work_order_id | bigint | NO |  |
| user_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (work_order_id, user_id)
- PRIMARY KEY (id)
- FOREIGN KEY (user_id) REFERENCES fd_users(id) ON DELETE CASCADE
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE CASCADE

**Indexes**

- fd_wo_assignments_work_order_id_user_id_unique (unique) (work_order_id, user_id)

### fd_wo_drawings

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| work_order_id | bigint | NO |  |
| original_name | character varying(255) | NO |  |
| file_path | character varying(255) | NO |  |
| file_size | bigint | yes |  |
| file_mime | character varying(255) | yes |  |
| uploaded_by | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE CASCADE

**Indexes**

- fd_wo_drawings_work_order_id_index (work_order_id)

### fd_wo_elevations

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| work_order_id | bigint | NO |  |
| elevation_type_id | bigint | yes |  |
| elevation_tag | character varying(255) | NO |  |
| quantity | integer | NO | 1 |
| date_requested | date | yes |  |
| date_completed | date | yes |  |
| completed_by_id | bigint | yes |  |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| scope | character varying(255) | NO | 'assemble' |
| template_set_id | bigint | yes |  |
| joint_qty | integer | yes |  |
| door_frame_configuration_id | bigint | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (completed_by_id) REFERENCES fd_users(id) ON DELETE SET NULL
- FOREIGN KEY (door_frame_configuration_id) REFERENCES door_frame_configurations(id) ON DELETE SET NULL
- FOREIGN KEY (elevation_type_id) REFERENCES fd_elevation_types(id) ON DELETE SET NULL
- FOREIGN KEY (template_set_id) REFERENCES fd_stage_template_sets(id) ON DELETE SET NULL
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE CASCADE

**Indexes**

- fd_wo_elevations_work_order_id_index (work_order_id)

### fd_wo_stage_assignees

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| stage_id | bigint | NO |  |
| user_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (stage_id, user_id)
- PRIMARY KEY (id)
- FOREIGN KEY (stage_id) REFERENCES fd_wo_stages(id) ON DELETE CASCADE
- FOREIGN KEY (user_id) REFERENCES fd_users(id) ON DELETE CASCADE

**Indexes**

- fd_wo_stage_assignees_stage_id_user_id_unique (unique) (stage_id, user_id)

### fd_wo_stages

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| work_order_id | bigint | yes |  |
| template_id | bigint | yes |  |
| name | character varying(255) | NO |  |
| description | text | yes |  |
| sort_order | integer | NO | 0 |
| status | character varying(255) | NO | 'pending' |
| assigned_to_id | bigint | yes |  |
| started_at | timestamp(0) without time zone | yes |  |
| completed_at | timestamp(0) without time zone | yes |  |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| elevation_id | bigint | yes |  |
| completed_by_id | bigint | yes |  |
| blocks_next | boolean | NO | true |
| minutes_per_joint | numeric(8,2) | yes |  |
| phase | integer | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (assigned_to_id) REFERENCES fd_users(id) ON DELETE SET NULL
- FOREIGN KEY (completed_by_id) REFERENCES fd_users(id) ON DELETE SET NULL
- FOREIGN KEY (elevation_id) REFERENCES fd_wo_elevations(id) ON DELETE CASCADE
- FOREIGN KEY (template_id) REFERENCES fd_stage_templates(id) ON DELETE SET NULL
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE CASCADE

**Indexes**

- fd_wo_stages_status_index (status)
- fd_wo_stages_work_order_id_index (work_order_id)

### fd_wo_status_log

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| work_order_id | bigint | NO |  |
| user_id | bigint | yes |  |
| from_status | character varying(255) | yes |  |
| to_status | character varying(255) | NO |  |
| note | text | yes |  |
| created_at | timestamp(0) without time zone | NO | CURRENT_TIMESTAMP |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE CASCADE

**Indexes**

- fd_wo_status_log_work_order_id_index (work_order_id)

### fd_work_orders

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| material_arrived | character varying(255) | yes |  |
| notes | text | yes |  |
| archived | boolean | NO | false |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| business_job_id | bigint | yes |  |
| release_number | integer | NO | 1 |
| date_issued | date | yes |  |
| material_delivery | character varying(255) | yes |  |
| priority | integer | yes |  |
| due_date | date | yes |  |
| priority_locked | boolean | NO | false |
| estimated_minutes_override | integer | yes |  |
| release_code | character varying(50) | yes |  |
| status | character varying(255) | NO | 'active' |
| completed_at | timestamp(0) without time zone | yes |  |
| completed_by_user_id | bigint | yes |  |
| completion_email_sent_at | timestamp(0) without time zone | yes |  |
| planned_start_date | date | yes |  |
| planned_completion_date | date | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (business_job_id) REFERENCES business_jobs(id) ON DELETE SET NULL
- FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL

**Indexes**

- fd_work_orders_archived_index (archived)
- fd_work_orders_business_job_id_release_number_index (business_job_id, release_number)
- fd_work_orders_status_index (status)

### inventory_locations

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| product_id | bigint | NO |  |
| quantity | numeric(10,1) | NO | '0' |
| quantity_committed | numeric(10,1) | NO | '0' |
| is_primary | boolean | NO | false |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| storage_location_id | bigint | NO |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
- FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id) ON DELETE SET NULL

**Indexes**

- inventory_locations_is_primary_index (is_primary)
- inventory_locations_product_id_index (product_id)
- inventory_locations_storage_location_id_index (storage_location_id)

### inventory_transactions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| product_id | bigint | NO |  |
| type | character varying(255) | NO |  |
| quantity | numeric(10,1) | NO |  |
| quantity_before | numeric(10,1) | NO |  |
| quantity_after | numeric(10,1) | NO |  |
| reference_number | character varying(255) | yes |  |
| reference_type | character varying(255) | yes |  |
| reference_id | bigint | yes |  |
| notes | text | yes |  |
| user_id | bigint | yes |  |
| transaction_date | timestamp(0) without time zone | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| business_job_id | bigint | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (business_job_id) REFERENCES business_jobs(id) ON DELETE SET NULL
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
- CHECK (((type)::text = ANY (ARRAY[('receipt'::character varying)::text, ('shipment'::character varying)::text, ('adjustment'::character varying)::text, ('transfer'::character varying)::text, ('return'::character varying)::text, ('cycle_count'::character varying)::text, ('job_issue'::character varying)::text, ('issue'::character varying)::text, ('job_material_transfer'::character varying)::text, ('fulfillment'::character varying)::text])))

**Indexes**

- inventory_transactions_business_job_id_index (business_job_id)
- inventory_transactions_product_id_index (product_id)
- inventory_transactions_transaction_date_index (transaction_date)
- inventory_transactions_type_index (type)

### job_batches

| Column | Type | Null | Default |
|---|---|---|---|
| id | character varying(255) | NO |  |
| name | character varying(255) | NO |  |
| total_jobs | integer | NO |  |
| pending_jobs | integer | NO |  |
| failed_jobs | integer | NO |  |
| failed_job_ids | text | NO |  |
| options | text | yes |  |
| cancelled_at | integer | yes |  |
| created_at | integer | NO |  |
| finished_at | integer | yes |  |

**Constraints**

- PRIMARY KEY (id)

### job_documents

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| business_job_id | bigint | NO |  |
| doc_type | character varying(40) | NO |  |
| label | character varying(255) | yes |  |
| original_name | character varying(255) | NO |  |
| file_path | character varying(255) | NO |  |
| file_size | bigint | yes |  |
| file_mime | character varying(255) | yes |  |
| uploaded_by | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| job_reservation_id | bigint | yes |  |
| archived | boolean | NO | false |
| archived_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (business_job_id) REFERENCES business_jobs(id) ON DELETE CASCADE
- FOREIGN KEY (job_reservation_id) REFERENCES job_reservations(id) ON DELETE RESTRICT
- FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL

**Indexes**

- job_documents_business_job_id_doc_type_index (business_job_id, doc_type)

### job_reservation_items

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| reservation_id | bigint | NO |  |
| product_id | bigint | NO |  |
| requested_qty | numeric(10,1) | NO |  |
| committed_qty | numeric(10,1) | NO |  |
| consumed_qty | numeric(10,1) | NO | '0' |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (reservation_id, product_id)
- PRIMARY KEY (id)
- FOREIGN KEY (product_id) REFERENCES products(id)
- FOREIGN KEY (reservation_id) REFERENCES job_reservations(id) ON DELETE CASCADE

**Indexes**

- unique_reservation_product (unique) (reservation_id, product_id)
- job_reservation_items_product_id_index (product_id)
- job_reservation_items_reservation_id_index (reservation_id)

### job_reservations

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| job_number | character varying(100) | NO |  |
| release_number | integer | NO |  |
| job_name | character varying(255) | NO |  |
| requested_by | character varying(255) | NO |  |
| needed_by | date | yes |  |
| status | character varying(255) | NO | 'active' |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| business_job_id | bigint | yes |  |
| reservation_id | integer | NO |  |
| requested_by_id | bigint | yes |  |
| fulfilled_by_id | bigint | yes |  |
| fulfilled_at | timestamp(0) without time zone | yes |  |
| source | character varying(30) | yes |  |

**Constraints**

- UNIQUE (job_number, release_number)
- UNIQUE (business_job_id, reservation_id)
- PRIMARY KEY (id)
- FOREIGN KEY (business_job_id) REFERENCES business_jobs(id) ON DELETE RESTRICT
- FOREIGN KEY (fulfilled_by_id) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (requested_by_id) REFERENCES users(id) ON DELETE SET NULL
- CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('active'::character varying)::text, ('in_progress'::character varying)::text, ('fulfilled'::character varying)::text, ('on_hold'::character varying)::text, ('cancelled'::character varying)::text])))

**Indexes**

- unique_job_release (unique) (job_number, release_number)
- unique_job_reservation_id (unique) (business_job_id, reservation_id)
- job_reservations_business_job_id_index (business_job_id)
- job_reservations_business_job_id_reservation_id_index (business_job_id, reservation_id)
- job_reservations_needed_by_index (needed_by)
- job_reservations_one_configurator_per_job (unique) (business_job_id) WHERE (((source)::text = 'configurator'::text) AND (deleted_at IS NULL))
- job_reservations_source_index (source)
- job_reservations_status_index (status)

### jobs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| queue | character varying(255) | NO |  |
| payload | text | NO |  |
| attempts | smallint | NO |  |
| reserved_at | integer | yes |  |
| available_at | integer | NO |  |
| created_at | integer | NO |  |

**Constraints**

- PRIMARY KEY (id)

**Indexes**

- jobs_queue_index (queue)

### machine_tooling

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| machine_id | bigint | NO |  |
| product_id | bigint | NO |  |
| location_on_machine | character varying(255) | NO |  |
| installed_at | timestamp(0) without time zone | NO |  |
| installed_by | character varying(255) | yes |  |
| maintenance_record_id | bigint | yes |  |
| tool_life_used | numeric(10,2) | NO | '0' |
| tool_life_remaining | numeric(10,2) | yes |  |
| status | character varying(255) | NO | 'active' |
| removed_at | timestamp(0) without time zone | yes |  |
| removed_by | character varying(255) | yes |  |
| replacement_maintenance_record_id | bigint | yes |  |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
- FOREIGN KEY (maintenance_record_id) REFERENCES maintenance_records(id) ON DELETE SET NULL
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
- FOREIGN KEY (replacement_maintenance_record_id) REFERENCES maintenance_records(id) ON DELETE SET NULL
- CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('warning'::character varying)::text, ('needs_replacement'::character varying)::text, ('replaced'::character varying)::text])))

**Indexes**

- machine_tooling_installed_at_index (installed_at)
- machine_tooling_machine_id_index (machine_id)
- machine_tooling_machine_id_status_index (machine_id, status)
- machine_tooling_product_id_index (product_id)
- machine_tooling_status_index (status)

### machine_types

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (name)
- PRIMARY KEY (id)

**Indexes**

- machine_types_name_unique (unique) (name)

### machines

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| equipment_type | character varying(255) | NO |  |
| machine_type_id | bigint | yes |  |
| manufacturer | character varying(255) | yes |  |
| model | character varying(255) | yes |  |
| serial_number | character varying(255) | yes |  |
| location | character varying(255) | yes |  |
| documents | json | yes |  |
| notes | text | yes |  |
| total_downtime_minutes | integer | NO | 0 |
| last_service_at | timestamp(0) without time zone | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (machine_type_id) REFERENCES machine_types(id) ON DELETE SET NULL

**Indexes**

- machines_equipment_type_index (equipment_type)
- machines_location_index (location)
- machines_machine_type_id_index (machine_type_id)
- machines_name_index (name)

### maintenance_records

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| machine_id | bigint | NO |  |
| task_id | bigint | yes |  |
| asset_id | bigint | yes |  |
| performed_by_old | character varying(255) | yes |  |
| performed_at | date | yes |  |
| notes | text | yes |  |
| downtime_minutes | integer | yes |  |
| labor_hours | numeric(8,2) | yes |  |
| parts_used | json | yes |  |
| attachments | json | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| performed_by | bigint | yes |  |
| tools_replaced | json | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
- FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
- FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (task_id) REFERENCES maintenance_tasks(id) ON DELETE SET NULL

**Indexes**

- maintenance_records_asset_id_index (asset_id)
- maintenance_records_machine_id_index (machine_id)
- maintenance_records_performed_at_index (performed_at)
- maintenance_records_performed_by_index (performed_by)
- maintenance_records_task_id_index (task_id)

### maintenance_tasks

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| machine_id | bigint | NO |  |
| title | character varying(255) | NO |  |
| description | text | yes |  |
| frequency | character varying(255) | yes |  |
| assigned_to_old | character varying(255) | yes |  |
| interval_count | integer | yes |  |
| interval_unit | character varying(255) | yes |  |
| start_date | date | yes |  |
| status | character varying(255) | NO | 'active' |
| priority | character varying(255) | NO | 'medium' |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| assigned_to | bigint | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
- CHECK (((interval_unit)::text = ANY (ARRAY[('day'::character varying)::text, ('week'::character varying)::text, ('month'::character varying)::text, ('year'::character varying)::text])))
- CHECK (((priority)::text = ANY (ARRAY[('low'::character varying)::text, ('medium'::character varying)::text, ('high'::character varying)::text, ('critical'::character varying)::text])))
- CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('paused'::character varying)::text, ('retired'::character varying)::text])))

**Indexes**

- maintenance_tasks_assigned_to_index (assigned_to)
- maintenance_tasks_machine_id_index (machine_id)
- maintenance_tasks_priority_index (priority)
- maintenance_tasks_start_date_index (start_date)
- maintenance_tasks_status_index (status)

### migrations

| Column | Type | Null | Default |
|---|---|---|---|
| id | integer | NO | auto-increment |
| migration | character varying(255) | NO |  |
| batch | integer | NO |  |

**Constraints**

- PRIMARY KEY (id)

### order_items

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| order_id | bigint | NO |  |
| product_id | bigint | NO |  |
| quantity | integer | NO |  |
| quantity_committed | integer | NO | 0 |
| quantity_shipped | integer | NO | 0 |
| unit_price | numeric(10,2) | NO |  |
| line_total | numeric(10,2) | NO |  |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT

**Indexes**

- order_items_order_id_index (order_id)
- order_items_product_id_index (product_id)

### orders

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| order_number | character varying(255) | NO |  |
| customer_name | character varying(255) | NO |  |
| customer_email | character varying(255) | yes |  |
| customer_phone | character varying(255) | yes |  |
| shipping_address | text | yes |  |
| status | character varying(255) | NO | 'pending' |
| priority | character varying(255) | NO | 'normal' |
| subtotal | numeric(10,2) | NO | '0' |
| tax | numeric(10,2) | NO | '0' |
| shipping | numeric(10,2) | NO | '0' |
| total | numeric(10,2) | NO | '0' |
| order_date | date | NO |  |
| expected_ship_date | date | yes |  |
| actual_ship_date | date | yes |  |
| notes | text | yes |  |
| user_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (order_number)
- PRIMARY KEY (id)
- FOREIGN KEY (user_id) REFERENCES users(id)
- CHECK (((priority)::text = ANY (ARRAY[('low'::character varying)::text, ('normal'::character varying)::text, ('high'::character varying)::text, ('urgent'::character varying)::text])))
- CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('processing'::character varying)::text, ('ready'::character varying)::text, ('shipped'::character varying)::text, ('completed'::character varying)::text, ('cancelled'::character varying)::text])))

**Indexes**

- orders_order_number_unique (unique) (order_number)
- orders_order_date_index (order_date)
- orders_order_number_index (order_number)
- orders_status_index (status)

### password_reset_tokens

| Column | Type | Null | Default |
|---|---|---|---|
| email | character varying(255) | NO |  |
| token | character varying(255) | NO |  |
| created_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (email)

### permissions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| display_name | character varying(255) | NO |  |
| category | character varying(255) | NO | 'general' |
| description | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (name)
- PRIMARY KEY (id)

**Indexes**

- permissions_name_unique (unique) (name)

### personal_access_tokens

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| tokenable_type | character varying(255) | NO |  |
| tokenable_id | bigint | NO |  |
| name | text | NO |  |
| token | character varying(64) | NO |  |
| abilities | text | yes |  |
| last_used_at | timestamp(0) without time zone | yes |  |
| expires_at | timestamp(0) without time zone | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (token)
- PRIMARY KEY (id)

**Indexes**

- personal_access_tokens_token_unique (unique) (token)
- personal_access_tokens_expires_at_index (expires_at)
- personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)

### products

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| sku | character varying(255) | NO |  |
| description | character varying(255) | NO |  |
| long_description | text | yes |  |
| unit_cost | numeric(10,2) | NO | '0' |
| unit_price | numeric(10,2) | NO | '0' |
| quantity_on_hand | numeric(10,1) | NO | '0' |
| quantity_committed | numeric(10,1) | NO | '0' |
| minimum_quantity | integer | NO | 0 |
| maximum_quantity | integer | yes |  |
| unit_of_measure | character varying(255) | NO | 'EA' |
| supplier_sku | character varying(255) | yes |  |
| lead_time_days | integer | yes |  |
| is_active | boolean | NO | true |
| status | character varying(255) | NO | 'in_stock' |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| part_number | character varying(255) | yes |  |
| finish | character varying(255) | yes |  |
| pack_size | integer | NO | 1 |
| purchase_uom | character varying(255) | NO | 'EA' |
| stock_uom | character varying(255) | NO | 'EA' |
| min_order_qty | integer | yes |  |
| order_multiple | integer | yes |  |
| reorder_point | integer | yes |  |
| safety_stock | integer | yes |  |
| average_daily_use | numeric(10,2) | yes |  |
| on_order_qty | integer | NO | 0 |
| supplier_id | bigint | NO |  |
| category_id | bigint | yes |  |
| dimension_height | numeric(10,2) | yes |  |
| dimension_depth | numeric(10,2) | yes |  |
| is_discontinued | boolean | NO | false |
| supplier_contact | character varying(255) | yes |  |
| tool_type | character varying(255) | yes |  |
| tool_life_max | numeric(10,2) | yes |  |
| tool_life_unit | character varying(255) | yes |  |
| tool_life_warning_threshold | integer | yes |  |
| compatible_machine_types | json | yes |  |
| tool_specifications | json | yes |  |
| manufacturer | character varying(255) | yes |  |
| manufacturer_part_number | character varying(255) | yes |  |
| net_cost | numeric(10,4) | yes |  |
| pricing_category | character varying(255) | yes |  |
| finish_multiplier | numeric(10,4) | yes |  |
| category_multiplier | numeric(10,4) | yes |  |
| price_per_length | numeric(10,4) | yes |  |
| price_per_package | numeric(10,4) | yes |  |
| product_type | character varying(255) | NO | 'active' |
| photo_path | character varying(255) | yes |  |
| nonsof | boolean | NO | false |
| cp_part | boolean | NO | false |
| is_shared | boolean | NO | false |
| is_special_order | boolean | NO | false |
| configurator_length | numeric(10,4) | yes |  |
| configurator_weight_per_inch | numeric(10,4) | yes |  |
| is_length_based | boolean | NO | false |
| minimum_drop_length | numeric(10,4) | yes |  |
| cost_code | character varying(255) | yes |  |
| drop_rack_enabled | boolean | NO | false |
| drop_min_split | numeric(10,4) | yes |  |
| drop_max_length | numeric(10,4) | yes |  |

**Constraints**

- UNIQUE (sku)
- PRIMARY KEY (id)
- FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
- FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
- CHECK (((product_type)::text = ANY (ARRAY[('active'::character varying)::text, ('inactive'::character varying)::text, ('special_order'::character varying)::text, ('obsolete'::character varying)::text])))
- CHECK (((status)::text = ANY (ARRAY[('in_stock'::character varying)::text, ('low'::character varying)::text, ('very_low'::character varying)::text, ('critical'::character varying)::text, ('out_of_stock'::character varying)::text, ('on_order'::character varying)::text])))
- CHECK (((tool_life_unit)::text = ANY (ARRAY[('seconds'::character varying)::text, ('minutes'::character varying)::text, ('hours'::character varying)::text, ('cycles'::character varying)::text, ('parts'::character varying)::text, ('meters'::character varying)::text])))
- CHECK (((tool_type)::text = ANY (ARRAY[('consumable_tool'::character varying)::text, ('asset_tool'::character varying)::text])))

**Indexes**

- products_sku_unique (unique) (sku)
- products_category_id_index (category_id)
- products_finish_index (finish)
- products_part_number_index (part_number)
- products_sku_index (sku)
- products_status_index (status)
- products_supplier_id_index (supplier_id)
- products_tool_type_index (tool_type)

### purchase_order_items

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| purchase_order_id | bigint | NO |  |
| product_id | bigint | NO |  |
| quantity_ordered | integer | NO |  |
| quantity_received | integer | NO | 0 |
| unit_cost | numeric(10,2) | NO |  |
| total_cost | numeric(12,2) | NO |  |
| destination_location | character varying(255) | yes |  |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| cost_code | character varying(255) | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
- FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE

**Indexes**

- purchase_order_items_product_id_index (product_id)
- purchase_order_items_purchase_order_id_index (purchase_order_id)

### purchase_orders

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| po_number | character varying(255) | NO |  |
| supplier_id | bigint | yes |  |
| status | character varying(255) | NO | 'draft' |
| order_date | date | NO |  |
| expected_date | date | yes |  |
| received_date | date | yes |  |
| total_amount | numeric(12,2) | NO | '0' |
| notes | text | yes |  |
| ship_to | character varying(255) | yes |  |
| contact_name | character varying(255) | yes |  |
| contact_email | character varying(255) | yes |  |
| contact_phone | character varying(255) | yes |  |
| created_by | bigint | yes |  |
| approved_by | bigint | yes |  |
| approved_at | timestamp(0) without time zone | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| ship_to_location_id | bigint | yes |  |
| approver_id | bigint | yes |  |
| job_name | character varying(255) | yes |  |
| cost_code | character varying(255) | yes |  |

**Constraints**

- UNIQUE (po_number)
- PRIMARY KEY (id)
- FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (ship_to_location_id) REFERENCES company_locations(id) ON DELETE SET NULL
- FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
- CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('submitted'::character varying)::text, ('approved'::character varying)::text, ('partially_received'::character varying)::text, ('received'::character varying)::text, ('cancelled'::character varying)::text])))

**Indexes**

- purchase_orders_po_number_unique (unique) (po_number)
- purchase_orders_order_date_index (order_date)
- purchase_orders_po_number_index (po_number)
- purchase_orders_status_index (status)
- purchase_orders_supplier_id_index (supplier_id)

### quality_joint_history

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| month | character varying(7) | NO |  |
| joint_count | integer | NO |  |
| note | character varying(255) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| as_of_date | date | yes |  |

**Constraints**

- UNIQUE (month)
- PRIMARY KEY (id)

**Indexes**

- quality_joint_history_month_unique (unique) (month)

### quality_report_files

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| quality_report_id | bigint | NO |  |
| original_name | character varying(255) | NO |  |
| file_path | character varying(255) | NO |  |
| file_size | bigint | yes |  |
| file_mime | character varying(255) | yes |  |
| uploaded_by | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (quality_report_id) REFERENCES quality_reports(id) ON DELETE CASCADE
- FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL

**Indexes**

- quality_report_files_quality_report_id_index (quality_report_id)

### quality_reports

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| elevation_id | bigint | yes |  |
| work_order_id | bigint | yes |  |
| status | character varying(20) | NO | 'pending_review' |
| report_date | date | yes |  |
| completed_at | timestamp(0) without time zone | yes |  |
| inspector_name | character varying(255) | yes |  |
| problem_type | character varying(60) | yes |  |
| replacement_needed | boolean | yes |  |
| issue_description | text | yes |  |
| raw_extracted_text | text | yes |  |
| extracted_fields | json | yes |  |
| elevation_tag_guess | character varying(255) | yes |  |
| auto_matched | boolean | NO | true |
| match_confidence | numeric(5,2) | yes |  |
| match_candidates | json | yes |  |
| matched_by_user_id | bigint | yes |  |
| verified_by | bigint | yes |  |
| verified_at | timestamp(0) without time zone | yes |  |
| rejected_reason | text | yes |  |
| uploaded_by | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| reviewed_by | bigint | yes |  |
| reviewed_at | timestamp(0) without time zone | yes |  |
| pre_forge_completed_date | date | yes |  |
| job_text_guess | character varying(255) | yes |  |
| is_pre_forge | boolean | NO | false |
| business_job_id | bigint | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (business_job_id) REFERENCES business_jobs(id) ON DELETE SET NULL
- FOREIGN KEY (elevation_id) REFERENCES fd_wo_elevations(id) ON DELETE SET NULL
- FOREIGN KEY (matched_by_user_id) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (work_order_id) REFERENCES fd_work_orders(id) ON DELETE SET NULL

**Indexes**

- quality_reports_elevation_id_index (elevation_id)
- quality_reports_report_date_index (report_date)
- quality_reports_status_index (status)
- quality_reports_work_order_id_index (work_order_id)

### required_parts

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| parent_product_id | bigint | NO |  |
| required_product_id | bigint | NO |  |
| quantity | numeric(10,2) | NO | '1' |
| finish_policy | character varying(255) | yes |  |
| specific_finish | character varying(255) | yes |  |
| sort_order | integer | NO | 0 |
| is_optional | boolean | NO | false |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (parent_product_id, required_product_id)
- PRIMARY KEY (id)
- FOREIGN KEY (parent_product_id) REFERENCES products(id) ON DELETE CASCADE
- FOREIGN KEY (required_product_id) REFERENCES products(id) ON DELETE CASCADE

**Indexes**

- required_parts_parent_product_id_required_product_id_unique (unique) (parent_product_id, required_product_id)
- required_parts_parent_product_id_index (parent_product_id)
- required_parts_required_product_id_index (required_product_id)

### role_permissions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| role_id | bigint | NO |  |
| permission_id | bigint | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (role_id, permission_id)
- PRIMARY KEY (id)
- FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
- FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE

**Indexes**

- role_permissions_role_id_permission_id_unique (unique) (role_id, permission_id)

### roles

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| display_name | character varying(255) | NO |  |
| description | text | yes |  |
| is_system | boolean | NO | false |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (name)
- PRIMARY KEY (id)

**Indexes**

- roles_name_unique (unique) (name)

### sessions

| Column | Type | Null | Default |
|---|---|---|---|
| id | character varying(255) | NO |  |
| user_id | bigint | yes |  |
| ip_address | character varying(45) | yes |  |
| user_agent | text | yes |  |
| payload | text | NO |  |
| last_activity | integer | NO |  |

**Constraints**

- PRIMARY KEY (id)

**Indexes**

- sessions_last_activity_index (last_activity)
- sessions_user_id_index (user_id)

### storage_locations

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| code | character varying(255) | yes |  |
| type | character varying(255) | NO | 'bin' |
| description | text | yes |  |
| aisle | character varying(255) | yes |  |
| bay | character varying(255) | yes |  |
| level | character varying(255) | yes |  |
| position | character varying(255) | yes |  |
| capacity | numeric(10,2) | yes |  |
| capacity_unit | character varying(255) | yes |  |
| is_active | boolean | NO | true |
| sort_order | integer | NO | 0 |
| notes | text | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| parent_id | bigint | yes |  |
| path | character varying(255) | yes |  |
| depth | integer | NO | 0 |

**Constraints**

- UNIQUE (code)
- UNIQUE (name)
- PRIMARY KEY (id)
- FOREIGN KEY (parent_id) REFERENCES storage_locations(id) ON DELETE CASCADE

**Indexes**

- storage_locations_code_unique (unique) (code)
- storage_locations_name_unique (unique) (name)
- storage_locations_aisle_index (aisle)
- storage_locations_code_index (code)
- storage_locations_depth_index (depth)
- storage_locations_is_active_index (is_active)
- storage_locations_name_index (name)
- storage_locations_parent_id_index (parent_id)
- storage_locations_path_index (path)
- storage_locations_type_index (type)

### suppliers

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| code | character varying(255) | yes |  |
| contact_name | character varying(255) | yes |  |
| contact_email | character varying(255) | yes |  |
| contact_phone | character varying(255) | yes |  |
| address | text | yes |  |
| city | character varying(255) | yes |  |
| state | character varying(255) | yes |  |
| zip | character varying(255) | yes |  |
| country | character varying(255) | NO | 'USA' |
| website | character varying(255) | yes |  |
| notes | text | yes |  |
| default_lead_time_days | integer | yes |  |
| minimum_order_amount | numeric(10,2) | yes |  |
| is_active | boolean | NO | true |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| fax | character varying(255) | yes |  |

**Constraints**

- UNIQUE (code)
- PRIMARY KEY (id)

**Indexes**

- suppliers_code_unique (unique) (code)
- suppliers_code_index (code)
- suppliers_is_active_index (is_active)
- suppliers_name_index (name)

### system_notifications

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| type | character varying(255) | NO |  |
| level | character varying(255) | NO | 'warning' |
| title | character varying(255) | NO |  |
| message | text | NO |  |
| data | json | yes |  |
| dismissed_at | timestamp(0) without time zone | yes |  |
| dismissed_by_user_id | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (dismissed_by_user_id) REFERENCES users(id) ON DELETE SET NULL

**Indexes**

- system_notifications_type_dismissed_at_index (type, dismissed_at)

### users

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| email | character varying(255) | NO |  |
| email_verified_at | timestamp(0) without time zone | yes |  |
| password | character varying(255) | NO |  |
| remember_token | character varying(100) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| first_name | character varying(255) | yes |  |
| last_name | character varying(255) | yes |  |
| role | character varying(255) | NO | 'viewer' |
| is_active | boolean | NO | true |
| last_login_at | timestamp(0) without time zone | yes |  |
| deleted_at | timestamp(0) without time zone | yes |  |
| must_change_password | boolean | NO | false |
| password_set_at | timestamp(0) without time zone | yes |  |
| welcome_email_sent_at | timestamp(0) without time zone | yes |  |
| theme_preferences | json | yes |  |
| wo_column_prefs | json | yes |  |
| quality_report_prefs | json | yes |  |
| jobs_column_prefs | json | yes |  |
| dashboard_prefs | json | yes |  |
| is_disabled | boolean | NO | false |

**Constraints**

- UNIQUE (email)
- PRIMARY KEY (id)

**Indexes**

- users_email_unique (unique) (email)

## CutFlow database: cutflow

8 tables.

### cut_consumptions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| cut_log_entry_id | bigint | NO |  |
| cut_job_id | bigint | NO |  |
| product_id | bigint | NO |  |
| stock_fraction | numeric(10,4) | NO |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (cut_log_entry_id)
- PRIMARY KEY (id)

**Indexes**

- cut_consumptions_cut_log_entry_id_unique (unique) (cut_log_entry_id)
- cut_consumptions_cut_job_id_product_id_index (cut_job_id, product_id)
- cut_consumptions_product_id_index (product_id)

### cut_jobs

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| name | character varying(255) | NO |  |
| work_order_id | bigint | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| bom_diverged_at | timestamp(0) without time zone | yes |  |

**Constraints**

- UNIQUE (work_order_id)
- PRIMARY KEY (id)

**Indexes**

- cut_jobs_work_order_id_unique (unique) (work_order_id)

### cut_log_entries

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| uuid | uuid | yes |  |
| part_id | bigint | yes |  |
| part_name | character varying(255) | yes |  |
| finish | character varying(255) | yes |  |
| operator_id | bigint | yes |  |
| operator_name | character varying(255) | yes |  |
| cut_job_id | bigint | yes |  |
| job_name | character varying(255) | yes |  |
| work_order | character varying(255) | yes |  |
| phase | character varying(255) | yes |  |
| description | character varying(255) | yes |  |
| dimension_inches | numeric(8,3) | NO |  |
| stick_length_label | character varying(255) | yes |  |
| stick_session_id | bigint | yes |  |
| type | character varying(255) | NO | 'planned' |
| is_recut | boolean | NO | false |
| is_reprint | boolean | NO | false |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| is_manual_cut | boolean | NO | false |

**Constraints**

- UNIQUE (uuid)
- PRIMARY KEY (id)
- FOREIGN KEY (cut_job_id) REFERENCES cut_jobs(id) ON DELETE SET NULL
- FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE SET NULL
- FOREIGN KEY (stick_session_id) REFERENCES stick_sessions(id) ON DELETE SET NULL
- CHECK (((type)::text = ANY (ARRAY['planned'::text, 'manual'::text, 'drop'::text])))

**Indexes**

- cut_log_entries_uuid_unique (unique) (uuid)

### cutflow_settings

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| cut_sensor_active | boolean | NO | false |
| show_cut_toast | boolean | NO | true |
| kerf_inches | numeric(6,3) | yes |  |
| standard_stock_length | numeric(8,3) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| bridge_url | character varying(255) | yes |  |
| bridge_token | character varying(255) | yes |  |
| bridge_fake | boolean | NO | false |
| tablet_allowed_ips | text | yes |  |
| optimizer | character varying(10) | NO | 'old' |

**Constraints**

- PRIMARY KEY (id)

### migrations

| Column | Type | Null | Default |
|---|---|---|---|
| id | integer | NO | auto-increment |
| migration | character varying(255) | NO |  |
| batch | integer | NO |  |

**Constraints**

- PRIMARY KEY (id)

### parts

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| cut_job_id | bigint | NO |  |
| name | character varying(255) | NO |  |
| finish | character varying(255) | yes |  |
| dimension_inches | numeric(8,3) | NO |  |
| qty_original | integer | NO | 0 |
| qty_remaining | integer | NO | 0 |
| work_order | character varying(255) | yes |  |
| phase | character varying(255) | yes |  |
| description | character varying(255) | yes |  |
| row | integer | yes |  |
| column | integer | yes |  |
| left_cut_angle | numeric(5,2) | yes |  |
| right_cut_angle | numeric(5,2) | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| source | character varying(20) | yes |  |

**Constraints**

- UNIQUE (cut_job_id, name, finish, dimension_inches, description)
- PRIMARY KEY (id)
- FOREIGN KEY (cut_job_id) REFERENCES cut_jobs(id) ON DELETE CASCADE

**Indexes**

- parts_job_profile_dimension_use_unique (unique) (cut_job_id, name, finish, dimension_inches, description)

### stick_items

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| stick_session_id | bigint | NO |  |
| part_id | bigint | yes |  |
| dimension_inches | numeric(8,3) | NO |  |
| sequence | integer | NO |  |
| status | character varying(255) | NO | 'pending' |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| pending_uuid | uuid | yes |  |
| label_printed_at | timestamp(0) without time zone | yes |  |
| kind | character varying(10) | NO | 'piece' |

**Constraints**

- PRIMARY KEY (id)
- FOREIGN KEY (part_id) REFERENCES parts(id)
- FOREIGN KEY (stick_session_id) REFERENCES stick_sessions(id) ON DELETE CASCADE
- CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('done'::character varying)::text])))

### stick_sessions

| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint | NO | auto-increment |
| length_label | character varying(255) | NO |  |
| part_name | character varying(255) | NO |  |
| finish | character varying(255) | yes |  |
| length_inches | numeric(8,3) | NO |  |
| waste_inches | numeric(8,3) | yes |  |
| status | character varying(255) | NO | 'active' |
| cancelled_at | timestamp(0) without time zone | yes |  |
| created_at | timestamp(0) without time zone | yes |  |
| updated_at | timestamp(0) without time zone | yes |  |
| drop_plan | json | yes |  |

**Constraints**

- PRIMARY KEY (id)
- CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('complete'::character varying)::text])))
