// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'regions2.dart';
import 'districts2.dart';

part 'data190.g.dart';

@JsonSerializable()
class Data190 {
  const Data190({required this.regions, required this.districts});

  factory Data190.fromJson(Map<String, Object?> json) =>
      _$Data190FromJson(json);

  final List<Regions2> regions;
  final List<Districts2> districts;

  Map<String, Object?> toJson() => _$Data190ToJson(this);
}
