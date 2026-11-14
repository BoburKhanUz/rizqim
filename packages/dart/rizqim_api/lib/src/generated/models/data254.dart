// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bookings.dart';

part 'data254.g.dart';

@JsonSerializable()
class Data254 {
  const Data254({required this.bookings});

  factory Data254.fromJson(Map<String, Object?> json) =>
      _$Data254FromJson(json);

  final List<Bookings> bookings;

  Map<String, Object?> toJson() => _$Data254ToJson(this);
}
