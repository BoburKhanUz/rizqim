// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking2.dart';
import 'payment.dart';
import 'fiscal29.dart';

part 'data264.g.dart';

@JsonSerializable()
class Data264 {
  const Data264({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data264.fromJson(Map<String, Object?> json) =>
      _$Data264FromJson(json);

  final Booking2 booking;
  final Payment payment;
  final Fiscal29 fiscal;

  Map<String, Object?> toJson() => _$Data264ToJson(this);
}
