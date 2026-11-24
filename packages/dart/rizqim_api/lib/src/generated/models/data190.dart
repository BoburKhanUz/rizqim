// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'calendar.dart';
import 'tariffs.dart';

part 'data190.g.dart';

@JsonSerializable()
class Data190 {
  const Data190({required this.calendar, required this.tariffs});

  factory Data190.fromJson(Map<String, Object?> json) =>
      _$Data190FromJson(json);

  final Calendar calendar;
  final List<Tariffs> tariffs;

  Map<String, Object?> toJson() => _$Data190ToJson(this);
}
