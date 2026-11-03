// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking3.dart';
import 'payment2.dart';
import 'fiscal8.dart';

part 'data198.g.dart';

@JsonSerializable()
class Data198 {
  const Data198({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data198.fromJson(Map<String, Object?> json) =>
      _$Data198FromJson(json);

  final Booking3 booking;
  final Payment2 payment;
  final Fiscal8 fiscal;

  Map<String, Object?> toJson() => _$Data198ToJson(this);
}
