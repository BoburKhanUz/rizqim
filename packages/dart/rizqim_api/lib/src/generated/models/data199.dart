// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking4.dart';
import 'payment3.dart';
import 'fiscal9.dart';

part 'data199.g.dart';

@JsonSerializable()
class Data199 {
  const Data199({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data199.fromJson(Map<String, Object?> json) =>
      _$Data199FromJson(json);

  final Booking4 booking;
  final Payment3 payment;
  final Fiscal9 fiscal;

  Map<String, Object?> toJson() => _$Data199ToJson(this);
}
