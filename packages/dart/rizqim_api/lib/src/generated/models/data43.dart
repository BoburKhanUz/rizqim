// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'business_day.dart';
import 'days2.dart';

part 'data43.g.dart';

@JsonSerializable()
class Data43 {
  const Data43({
    required this.branchId,
    required this.businessDay,
    required this.days,
  });

  factory Data43.fromJson(Map<String, Object?> json) => _$Data43FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'business_day')
  final BusinessDay businessDay;
  final List<Days2> days;

  Map<String, Object?> toJson() => _$Data43ToJson(this);
}
