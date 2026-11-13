// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'session2.dart';

part 'data212.g.dart';

@JsonSerializable()
class Data212 {
  const Data212({
    required this.session,
    required this.todayMinutes,
    required this.businessDate,
  });

  factory Data212.fromJson(Map<String, Object?> json) =>
      _$Data212FromJson(json);

  final Session2? session;
  @JsonKey(name: 'today_minutes')
  final int todayMinutes;
  @JsonKey(name: 'business_date')
  final String businessDate;

  Map<String, Object?> toJson() => _$Data212ToJson(this);
}
