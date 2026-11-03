// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'business_day.g.dart';

@JsonSerializable()
class BusinessDay {
  const BusinessDay({
    required this.businessDate,
    required this.startsAt,
    required this.startedAt,
    required this.nextStartsAt,
    required this.roundTheClock,
  });

  factory BusinessDay.fromJson(Map<String, Object?> json) =>
      _$BusinessDayFromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'starts_at')
  final String startsAt;
  @JsonKey(name: 'started_at')
  final String startedAt;
  @JsonKey(name: 'next_starts_at')
  final String nextStartsAt;
  @JsonKey(name: 'round_the_clock')
  final bool roundTheClock;

  Map<String, Object?> toJson() => _$BusinessDayToJson(this);
}
