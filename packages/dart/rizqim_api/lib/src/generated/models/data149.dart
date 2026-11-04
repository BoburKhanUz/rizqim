// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'summary2.dart';
import 'orders.dart';
import 'stations8.dart';
import 'products7.dart';
import 'hours.dart';
import 'expo.dart';

part 'data149.g.dart';

@JsonSerializable()
class Data149 {
  const Data149({
    required this.branchId,
    required this.from,
    required this.to,
    required this.timezone,
    required this.dayStartsAt,
    required this.stationId,
    required this.summary,
    required this.orders,
    required this.stations,
    required this.products,
    required this.hours,
    required this.expo,
  });

  factory Data149.fromJson(Map<String, Object?> json) =>
      _$Data149FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final String timezone;
  @JsonKey(name: 'day_starts_at')
  final String dayStartsAt;
  @JsonKey(name: 'station_id')
  final int? stationId;
  final Summary2 summary;
  final Orders orders;
  final List<Stations8> stations;
  final List<Products7> products;
  final List<Hours> hours;
  final Expo? expo;

  Map<String, Object?> toJson() => _$Data149ToJson(this);
}
