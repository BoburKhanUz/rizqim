// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'session2.dart';

part 'data272.g.dart';

@JsonSerializable()
class Data272 {
  const Data272({
    required this.session,
    required this.todayMinutes,
    required this.businessDate,
  });

  factory Data272.fromJson(Map<String, Object?> json) =>
      _$Data272FromJson(json);

  final Session2? session;
  @JsonKey(name: 'today_minutes')
  final int todayMinutes;
  @JsonKey(name: 'business_date')
  final String businessDate;

  Map<String, Object?> toJson() => _$Data272ToJson(this);
}
