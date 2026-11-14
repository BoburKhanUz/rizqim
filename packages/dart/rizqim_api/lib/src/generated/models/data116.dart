// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kinds.dart';
import 'stations4.dart';

part 'data116.g.dart';

@JsonSerializable()
class Data116 {
  const Data116({required this.kinds, required this.stations});

  factory Data116.fromJson(Map<String, Object?> json) =>
      _$Data116FromJson(json);

  final List<Kinds> kinds;
  final List<Stations4> stations;

  Map<String, Object?> toJson() => _$Data116ToJson(this);
}
