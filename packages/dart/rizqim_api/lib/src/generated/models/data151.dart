// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data151.g.dart';

@JsonSerializable()
class Data151 {
  const Data151({
    required this.branchId,
    required this.weekendDays,
    required this.holidays,
  });

  factory Data151.fromJson(Map<String, Object?> json) =>
      _$Data151FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'weekend_days')
  final List<int> weekendDays;
  final List<String> holidays;

  Map<String, Object?> toJson() => _$Data151ToJson(this);
}
