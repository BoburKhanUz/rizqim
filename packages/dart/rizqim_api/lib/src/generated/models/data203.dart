// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking2.dart';
import 'payment.dart';
import 'fiscal9.dart';

part 'data203.g.dart';

@JsonSerializable()
class Data203 {
  const Data203({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data203.fromJson(Map<String, Object?> json) =>
      _$Data203FromJson(json);

  final Booking2 booking;
  final Payment payment;
  final Fiscal9 fiscal;

  Map<String, Object?> toJson() => _$Data203ToJson(this);
}
