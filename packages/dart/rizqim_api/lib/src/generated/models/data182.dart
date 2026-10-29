// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking4.dart';
import 'payment3.dart';
import 'fiscal8.dart';

part 'data182.g.dart';

@JsonSerializable()
class Data182 {
  const Data182({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data182.fromJson(Map<String, Object?> json) =>
      _$Data182FromJson(json);

  final Booking4 booking;
  final Payment3 payment;
  final Fiscal8 fiscal;

  Map<String, Object?> toJson() => _$Data182ToJson(this);
}
