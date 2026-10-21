// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'series.g.dart';

@JsonSerializable()
class Series {
  const Series({
    required this.date,
    required this.orders,
    required this.delivered,
    required this.revenue,
  });

  factory Series.fromJson(Map<String, Object?> json) => _$SeriesFromJson(json);

  final String date;
  final int orders;
  final int delivered;
  final String revenue;

  Map<String, Object?> toJson() => _$SeriesToJson(this);
}
