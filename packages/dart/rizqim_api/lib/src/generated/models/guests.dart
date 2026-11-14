// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'guests.g.dart';

@JsonSerializable()
class Guests {
  const Guests({
    required this.guaranteed,
    required this.minimum,
    required this.actual,
    required this.billed,
  });

  factory Guests.fromJson(Map<String, Object?> json) => _$GuestsFromJson(json);

  final int guaranteed;
  final int minimum;
  final int? actual;
  final int billed;

  Map<String, Object?> toJson() => _$GuestsToJson(this);
}
