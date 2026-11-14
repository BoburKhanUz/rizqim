// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking4.dart';
import 'payment3.dart';
import 'fiscal29.dart';

part 'data265.g.dart';

@JsonSerializable()
class Data265 {
  const Data265({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data265.fromJson(Map<String, Object?> json) =>
      _$Data265FromJson(json);

  final Booking4 booking;
  final Payment3 payment;
  final Fiscal29 fiscal;

  Map<String, Object?> toJson() => _$Data265ToJson(this);
}
