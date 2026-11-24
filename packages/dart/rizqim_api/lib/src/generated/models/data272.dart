// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking3.dart';
import 'payment2.dart';
import 'fiscal30.dart';

part 'data272.g.dart';

@JsonSerializable()
class Data272 {
  const Data272({
    required this.booking,
    required this.payment,
    required this.fiscal,
  });

  factory Data272.fromJson(Map<String, Object?> json) =>
      _$Data272FromJson(json);

  final Booking3 booking;
  final Payment2 payment;
  final Fiscal30 fiscal;

  Map<String, Object?> toJson() => _$Data272ToJson(this);
}
