// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'regions2.dart';
import 'districts2.dart';

part 'data192.g.dart';

@JsonSerializable()
class Data192 {
  const Data192({required this.regions, required this.districts});

  factory Data192.fromJson(Map<String, Object?> json) =>
      _$Data192FromJson(json);

  final List<Regions2> regions;
  final List<Districts2> districts;

  Map<String, Object?> toJson() => _$Data192ToJson(this);
}
