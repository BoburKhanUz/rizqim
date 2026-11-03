// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bookings.dart';

part 'data187.g.dart';

@JsonSerializable()
class Data187 {
  const Data187({required this.bookings});

  factory Data187.fromJson(Map<String, Object?> json) =>
      _$Data187FromJson(json);

  final List<Bookings> bookings;

  Map<String, Object?> toJson() => _$Data187ToJson(this);
}
