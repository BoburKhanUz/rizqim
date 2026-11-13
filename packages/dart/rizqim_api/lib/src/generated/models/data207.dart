// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking.dart';
import 'order9.dart';

part 'data207.g.dart';

@JsonSerializable()
class Data207 {
  const Data207({required this.booking, required this.order});

  factory Data207.fromJson(Map<String, Object?> json) =>
      _$Data207FromJson(json);

  final Booking booking;
  final Order9 order;

  Map<String, Object?> toJson() => _$Data207ToJson(this);
}
