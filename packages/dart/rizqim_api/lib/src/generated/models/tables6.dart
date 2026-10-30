// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'layout12.dart';
import 'reservation.dart';
import 'orders.dart';

part 'tables6.g.dart';

@JsonSerializable()
class Tables6 {
  const Tables6({
    required this.id,
    required this.name,
    required this.kind,
    required this.seats,
    required this.layout,
    required this.isActive,
    required this.minSpend,
    required this.minSpendMode,
    required this.status,
    required this.billRequested,
    required this.serviceState,
    required this.reservation,
    required this.orders,
  });

  factory Tables6.fromJson(Map<String, Object?> json) =>
      _$Tables6FromJson(json);

  final int id;
  final String name;
  final String kind;
  final int? seats;
  final Layout12 layout;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'min_spend')
  final String? minSpend;
  @JsonKey(name: 'min_spend_mode')
  final String minSpendMode;
  final String status;
  @JsonKey(name: 'bill_requested')
  final bool billRequested;
  @JsonKey(name: 'service_state')
  final String? serviceState;
  final Reservation? reservation;
  final List<Orders> orders;

  Map<String, Object?> toJson() => _$Tables6ToJson(this);
}
