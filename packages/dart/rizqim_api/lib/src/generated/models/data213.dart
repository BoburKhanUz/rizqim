// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'session3.dart';

part 'data213.g.dart';

@JsonSerializable()
class Data213 {
  const Data213({
    required this.session,
    required this.todayMinutes,
    required this.businessDate,
  });

  factory Data213.fromJson(Map<String, Object?> json) =>
      _$Data213FromJson(json);

  final Session3? session;
  @JsonKey(name: 'today_minutes')
  final int todayMinutes;
  @JsonKey(name: 'business_date')
  final String businessDate;

  Map<String, Object?> toJson() => _$Data213ToJson(this);
}
