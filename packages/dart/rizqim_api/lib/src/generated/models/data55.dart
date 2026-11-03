// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data55.g.dart';

@JsonSerializable()
class Data55 {
  const Data55({
    required this.branchId,
    required this.configured,
    required this.size,
    required this.sizeLabel,
    required this.widthMm,
    required this.heightMm,
    required this.scope,
    required this.scopeLabel,
    required this.categoryIds,
    required this.perUnit,
    required this.bagLabel,
    required this.updatedAt,
  });

  factory Data55.fromJson(Map<String, Object?> json) => _$Data55FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool configured;
  final String size;
  @JsonKey(name: 'size_label')
  final String sizeLabel;
  @JsonKey(name: 'width_mm')
  final int widthMm;
  @JsonKey(name: 'height_mm')
  final int heightMm;
  final String scope;
  @JsonKey(name: 'scope_label')
  final String scopeLabel;
  @JsonKey(name: 'category_ids')
  final List<int> categoryIds;
  @JsonKey(name: 'per_unit')
  final bool perUnit;
  @JsonKey(name: 'bag_label')
  final bool bagLabel;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data55ToJson(this);
}
