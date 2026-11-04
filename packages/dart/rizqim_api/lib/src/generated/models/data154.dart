// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data154.g.dart';

@JsonSerializable()
class Data154 {
  const Data154({
    required this.branchId,
    required this.weekendDays,
    required this.holidays,
  });

  factory Data154.fromJson(Map<String, Object?> json) =>
      _$Data154FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'weekend_days')
  final List<int> weekendDays;
  final List<String> holidays;

  Map<String, Object?> toJson() => _$Data154ToJson(this);
}
