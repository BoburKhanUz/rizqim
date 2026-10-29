// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking4.dart';
import 'payment3.dart';
import 'fiscal8.dart';

part 'data153.g.dart';

@JsonSerializable()
class Data153 {
  const Data153({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data153.fromJson(Map<String, Object?> json) =>
      _$Data153FromJson(json);

  final Booking4 booking;
  final Payment3 payment;
  final Fiscal8 fiscal;

  Map<String, Object?> toJson() => _$Data153ToJson(this);
}
