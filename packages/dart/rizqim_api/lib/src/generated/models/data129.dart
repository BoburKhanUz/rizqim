// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking2.dart';
import 'payment.dart';
import 'fiscal5.dart';

part 'data129.g.dart';

@JsonSerializable()
class Data129 {
  const Data129({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data129.fromJson(Map<String, Object?> json) =>
      _$Data129FromJson(json);

  final Booking2 booking;
  final Payment payment;
  final Fiscal5 fiscal;

  Map<String, Object?> toJson() => _$Data129ToJson(this);
}
