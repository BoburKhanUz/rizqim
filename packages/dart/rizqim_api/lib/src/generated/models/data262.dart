// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking.dart';
import 'order9.dart';

part 'data262.g.dart';

@JsonSerializable()
class Data262 {
  const Data262({required this.booking, required this.order});

  factory Data262.fromJson(Map<String, Object?> json) =>
      _$Data262FromJson(json);

  final Booking booking;
  final Order9 order;

  Map<String, Object?> toJson() => _$Data262ToJson(this);
}
