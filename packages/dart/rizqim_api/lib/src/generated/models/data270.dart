// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking3.dart';
import 'payment2.dart';
import 'fiscal30.dart';

part 'data270.g.dart';

@JsonSerializable()
class Data270 {
  const Data270({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data270.fromJson(Map<String, Object?> json) =>
      _$Data270FromJson(json);

  final Booking3 booking;
  final Payment2 payment;
  final Fiscal30 fiscal;

  Map<String, Object?> toJson() => _$Data270ToJson(this);
}
