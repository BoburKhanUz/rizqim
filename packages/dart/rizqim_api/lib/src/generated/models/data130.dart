// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking3.dart';
import 'payment2.dart';

part 'data130.g.dart';

@JsonSerializable()
class Data130 {
  const Data130({required this.booking, required this.payment});

  factory Data130.fromJson(Map<String, Object?> json) =>
      _$Data130FromJson(json);

  final Booking3 booking;
  final Payment2 payment;

  Map<String, Object?> toJson() => _$Data130ToJson(this);
}
