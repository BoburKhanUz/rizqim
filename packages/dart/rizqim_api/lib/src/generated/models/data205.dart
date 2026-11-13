// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking4.dart';
import 'payment3.dart';
import 'fiscal11.dart';

part 'data205.g.dart';

@JsonSerializable()
class Data205 {
  const Data205({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data205.fromJson(Map<String, Object?> json) =>
      _$Data205FromJson(json);

  final Booking4 booking;
  final Payment3 payment;
  final Fiscal11 fiscal;

  Map<String, Object?> toJson() => _$Data205ToJson(this);
}
