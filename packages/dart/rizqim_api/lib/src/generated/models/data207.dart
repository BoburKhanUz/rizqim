// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'session4.dart';

part 'data207.g.dart';

@JsonSerializable()
class Data207 {
  const Data207({
    required this.session,
    required this.todayMinutes,
    required this.businessDate,
  });

  factory Data207.fromJson(Map<String, Object?> json) =>
      _$Data207FromJson(json);

  final Session4? session;
  @JsonKey(name: 'today_minutes')
  final int todayMinutes;
  @JsonKey(name: 'business_date')
  final String businessDate;

  Map<String, Object?> toJson() => _$Data207ToJson(this);
}
