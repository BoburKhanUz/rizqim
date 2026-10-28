// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'calendar.dart';
import 'tariffs.dart';

part 'data100.g.dart';

@JsonSerializable()
class Data100 {
  const Data100({required this.calendar, required this.tariffs});

  factory Data100.fromJson(Map<String, Object?> json) =>
      _$Data100FromJson(json);

  final Calendar calendar;
  final List<Tariffs> tariffs;

  Map<String, Object?> toJson() => _$Data100ToJson(this);
}
