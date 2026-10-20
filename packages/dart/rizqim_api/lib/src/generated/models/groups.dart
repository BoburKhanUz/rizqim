// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'groups.g.dart';

@JsonSerializable()
class Groups {
  const Groups({
    required this.modifierGroupId,
    this.minSelect,
    this.maxSelect,
    this.isRequired,
    this.sortOrder,
  });

  factory Groups.fromJson(Map<String, Object?> json) => _$GroupsFromJson(json);

  @JsonKey(name: 'modifier_group_id')
  final int modifierGroupId;
  @JsonKey(name: 'min_select')
  final int? minSelect;
  @JsonKey(name: 'max_select')
  final int? maxSelect;
  @JsonKey(name: 'is_required')
  final bool? isRequired;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;

  Map<String, Object?> toJson() => _$GroupsToJson(this);
}
