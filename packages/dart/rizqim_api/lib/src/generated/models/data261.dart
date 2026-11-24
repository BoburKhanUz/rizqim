// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bookings.dart';

part 'data261.g.dart';

@JsonSerializable()
class Data261 {
  const Data261({required this.bookings});

  factory Data261.fromJson(Map<String, Object?> json) =>
      _$Data261FromJson(json);

  final List<Bookings> bookings;

  Map<String, Object?> toJson() => _$Data261ToJson(this);
}
