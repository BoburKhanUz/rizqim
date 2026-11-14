// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking2.dart';
import 'payment.dart';
import 'fiscal27.dart';

part 'data263.g.dart';

@JsonSerializable()
class Data263 {
  const Data263({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data263.fromJson(Map<String, Object?> json) =>
      _$Data263FromJson(json);

  final Booking2 booking;
  final Payment payment;
  final Fiscal27 fiscal;

  Map<String, Object?> toJson() => _$Data263ToJson(this);
}
