// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event9.dart';
import 'guests2.dart';
import 'stations9.dart';
import 'osh5.dart';
import 'warnings16.dart';

part 'data239.g.dart';

@JsonSerializable()
class Data239 {
  const Data239({
    required this.event,
    required this.guests,
    required this.stations,
    required this.osh,
    required this.warnings,
  });

  factory Data239.fromJson(Map<String, Object?> json) =>
      _$Data239FromJson(json);

  final Event9 event;
  final Guests2 guests;
  final List<Stations9> stations;
  final Osh5? osh;
  final List<Warnings16> warnings;

  Map<String, Object?> toJson() => _$Data239ToJson(this);
}
