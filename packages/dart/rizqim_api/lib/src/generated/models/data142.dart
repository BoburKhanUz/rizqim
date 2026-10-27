// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bookings.dart';

part 'data142.g.dart';

@JsonSerializable()
class Data142 {
  const Data142({required this.bookings});

  factory Data142.fromJson(Map<String, Object?> json) =>
      _$Data142FromJson(json);

  final List<Bookings> bookings;

  Map<String, Object?> toJson() => _$Data142ToJson(this);
}
