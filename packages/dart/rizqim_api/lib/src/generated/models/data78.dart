// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kinds.dart';
import 'stations.dart';

part 'data78.g.dart';

@JsonSerializable()
class Data78 {
  const Data78({required this.kinds, required this.stations});

  factory Data78.fromJson(Map<String, Object?> json) => _$Data78FromJson(json);

  final List<Kinds> kinds;
  final List<Stations> stations;

  Map<String, Object?> toJson() => _$Data78ToJson(this);
}
