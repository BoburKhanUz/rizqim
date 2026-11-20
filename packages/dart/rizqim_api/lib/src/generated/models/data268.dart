// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking4.dart';
import 'payment3.dart';
import 'fiscal31.dart';

part 'data268.g.dart';

@JsonSerializable()
class Data268 {
  const Data268({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data268.fromJson(Map<String, Object?> json) =>
      _$Data268FromJson(json);

  final Booking4 booking;
  final Payment3 payment;
  final Fiscal31 fiscal;

  Map<String, Object?> toJson() => _$Data268ToJson(this);
}
