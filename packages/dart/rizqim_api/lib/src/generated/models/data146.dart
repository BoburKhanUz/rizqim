// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking.dart';
import 'order7.dart';

part 'data146.g.dart';

@JsonSerializable()
class Data146 {
  const Data146({required this.booking, required this.order});

  factory Data146.fromJson(Map<String, Object?> json) =>
      _$Data146FromJson(json);

  final Booking booking;
  final Order7 order;

  Map<String, Object?> toJson() => _$Data146ToJson(this);
}
