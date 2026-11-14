// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'session3.dart';

part 'data273.g.dart';

@JsonSerializable()
class Data273 {
  const Data273({
    required this.session,
    required this.todayMinutes,
    required this.businessDate,
  });

  factory Data273.fromJson(Map<String, Object?> json) =>
      _$Data273FromJson(json);

  final Session3? session;
  @JsonKey(name: 'today_minutes')
  final int todayMinutes;
  @JsonKey(name: 'business_date')
  final String businessDate;

  Map<String, Object?> toJson() => _$Data273ToJson(this);
}
