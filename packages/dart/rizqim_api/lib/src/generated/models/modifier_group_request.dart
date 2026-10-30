// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifier_display_mode.dart';
import 'modifiers41.dart';

part 'modifier_group_request.g.dart';

/// The body of POST /owner/modifier-groups and PUT /owner/modifier-groups/{id} (P1-21; P2-30: a form request, so the.
/// OpenAPI document and the generated clients carry it). The permission (menu.manage) is the route's; the checks that.
/// need the store (linked variants, bounds) stay in ModifierGroupController.
@JsonSerializable()
class ModifierGroupRequest {
  const ModifierGroupRequest({
    required this.name,
    this.nameI18n,
    this.minSelect,
    this.maxSelect,
    this.isRequired,
    this.displayMode,
    this.sortOrder,
    this.isActive,
    this.modifiers,
  });

  factory ModifierGroupRequest.fromJson(Map<String, Object?> json) =>
      _$ModifierGroupRequestFromJson(json);

  final String name;
  @JsonKey(name: 'name_i18n')
  final Map<String, String>? nameI18n;
  @JsonKey(name: 'min_select')
  final int? minSelect;
  @JsonKey(name: 'max_select')
  final int? maxSelect;
  @JsonKey(name: 'is_required')
  final bool? isRequired;

  /// P6-32 (quick_modifiers): where the options are shown on the till's tile; left out: unchanged (a new group: dialog).
  @JsonKey(name: 'display_mode')
  final ModifierDisplayMode? displayMode;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  final List<Modifiers41>? modifiers;

  Map<String, Object?> toJson() => _$ModifierGroupRequestToJson(this);
}
