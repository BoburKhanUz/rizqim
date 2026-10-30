// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kinds.dart';
import 'stations3.dart';

part 'data109.g.dart';

@JsonSerializable()
class Data109 {
  const Data109({required this.kinds, required this.stations});

  factory Data109.fromJson(Map<String, Object?> json) =>
      _$Data109FromJson(json);

  final List<Kinds> kinds;
  final List<Stations3> stations;

  Map<String, Object?> toJson() => _$Data109ToJson(this);
}
