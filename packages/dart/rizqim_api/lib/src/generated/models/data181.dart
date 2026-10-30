// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking3.dart';
import 'payment2.dart';
import 'fiscal7.dart';

part 'data181.g.dart';

@JsonSerializable()
class Data181 {
  const Data181({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data181.fromJson(Map<String, Object?> json) =>
      _$Data181FromJson(json);

  final Booking3 booking;
  final Payment2 payment;
  final Fiscal7 fiscal;

  Map<String, Object?> toJson() => _$Data181ToJson(this);
}
