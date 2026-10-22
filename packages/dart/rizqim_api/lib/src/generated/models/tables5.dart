// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'layout11.dart';
import 'reservation.dart';
import 'orders.dart';

part 'tables5.g.dart';

@JsonSerializable()
class Tables5 {
  const Tables5({
    required this.id,
    required this.name,
    required this.kind,
    required this.seats,
    required this.layout,
    required this.isActive,
    required this.minSpend,
    required this.minSpendMode,
    required this.status,
    required this.reservation,
    required this.orders,
  });

  factory Tables5.fromJson(Map<String, Object?> json) =>
      _$Tables5FromJson(json);

  final int id;
  final String name;
  final String kind;
  final int? seats;
  final Layout11 layout;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'min_spend')
  final String? minSpend;
  @JsonKey(name: 'min_spend_mode')
  final String minSpendMode;
  final String status;
  final Reservation? reservation;
  final List<Orders> orders;

  Map<String, Object?> toJson() => _$Tables5ToJson(this);
}
