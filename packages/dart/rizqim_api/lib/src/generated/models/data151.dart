// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking2.dart';
import 'payment.dart';
import 'fiscal6.dart';

part 'data151.g.dart';

@JsonSerializable()
class Data151 {
  const Data151({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data151.fromJson(Map<String, Object?> json) =>
      _$Data151FromJson(json);

  final Booking2 booking;
  final Payment payment;
  final Fiscal6 fiscal;

  Map<String, Object?> toJson() => _$Data151ToJson(this);
}
