// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kinds.dart';
import 'stations.dart';

part 'data99.g.dart';

@JsonSerializable()
class Data99 {
  const Data99({required this.kinds, required this.stations});

  factory Data99.fromJson(Map<String, Object?> json) => _$Data99FromJson(json);

  final List<Kinds> kinds;
  final List<Stations> stations;

  Map<String, Object?> toJson() => _$Data99ToJson(this);
}
