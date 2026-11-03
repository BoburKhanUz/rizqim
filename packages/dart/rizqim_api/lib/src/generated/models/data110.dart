// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kinds.dart';
import 'stations4.dart';

part 'data110.g.dart';

@JsonSerializable()
class Data110 {
  const Data110({required this.kinds, required this.stations});

  factory Data110.fromJson(Map<String, Object?> json) =>
      _$Data110FromJson(json);

  final List<Kinds> kinds;
  final List<Stations4> stations;

  Map<String, Object?> toJson() => _$Data110ToJson(this);
}
