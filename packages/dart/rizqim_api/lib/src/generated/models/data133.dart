// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking4.dart';
import 'payment3.dart';
import 'fiscal7.dart';

part 'data133.g.dart';

@JsonSerializable()
class Data133 {
  const Data133({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data133.fromJson(Map<String, Object?> json) =>
      _$Data133FromJson(json);

  final Booking4 booking;
  final Payment3 payment;
  final Fiscal7 fiscal;

  Map<String, Object?> toJson() => _$Data133ToJson(this);
}
