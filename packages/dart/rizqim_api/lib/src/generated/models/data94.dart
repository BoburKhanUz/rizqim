// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kinds.dart';
import 'stations.dart';

part 'data94.g.dart';

@JsonSerializable()
class Data94 {
  const Data94({required this.kinds, required this.stations});

  factory Data94.fromJson(Map<String, Object?> json) => _$Data94FromJson(json);

  final List<Kinds> kinds;
  final List<Stations> stations;

  Map<String, Object?> toJson() => _$Data94ToJson(this);
}
