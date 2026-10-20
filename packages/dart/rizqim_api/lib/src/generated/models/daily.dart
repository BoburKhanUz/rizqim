// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'daily.g.dart';

@JsonSerializable()
class Daily {
  const Daily({
    required this.date,
    required this.orders,
    required this.delivered,
    required this.revenue,
  });

  factory Daily.fromJson(Map<String, Object?> json) => _$DailyFromJson(json);

  final String date;
  final int orders;
  final int delivered;
  final num revenue;

  Map<String, Object?> toJson() => _$DailyToJson(this);
}
