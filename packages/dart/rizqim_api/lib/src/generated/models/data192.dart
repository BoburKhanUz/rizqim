// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking.dart';
import 'order7.dart';

part 'data192.g.dart';

@JsonSerializable()
class Data192 {
  const Data192({required this.booking, required this.order});

  factory Data192.fromJson(Map<String, Object?> json) =>
      _$Data192FromJson(json);

  final Booking booking;
  final Order7 order;

  Map<String, Object?> toJson() => _$Data192ToJson(this);
}
