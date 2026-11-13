// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking3.dart';
import 'payment2.dart';
import 'fiscal14.dart';

part 'data213.g.dart';

@JsonSerializable()
class Data213 {
  const Data213({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data213.fromJson(Map<String, Object?> json) =>
      _$Data213FromJson(json);

  final Booking3 booking;
  final Payment2 payment;
  final Fiscal14 fiscal;

  Map<String, Object?> toJson() => _$Data213ToJson(this);
}
