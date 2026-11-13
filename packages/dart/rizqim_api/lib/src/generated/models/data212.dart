// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking2.dart';
import 'payment.dart';
import 'fiscal13.dart';

part 'data212.g.dart';

@JsonSerializable()
class Data212 {
  const Data212({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data212.fromJson(Map<String, Object?> json) =>
      _$Data212FromJson(json);

  final Booking2 booking;
  final Payment payment;
  final Fiscal13 fiscal;

  Map<String, Object?> toJson() => _$Data212ToJson(this);
}
