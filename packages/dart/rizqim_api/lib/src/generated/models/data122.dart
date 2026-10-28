// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bookings.dart';

part 'data122.g.dart';

@JsonSerializable()
class Data122 {
  const Data122({required this.bookings});

  factory Data122.fromJson(Map<String, Object?> json) =>
      _$Data122FromJson(json);

  final List<Bookings> bookings;

  Map<String, Object?> toJson() => _$Data122ToJson(this);
}
