// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'package.dart';
import 'hall.dart';
import 'extra.dart';

part 'mxik.g.dart';

@JsonSerializable()
class Mxik {
  const Mxik({required this.package, required this.hall, required this.extra});

  factory Mxik.fromJson(Map<String, Object?> json) => _$MxikFromJson(json);

  final Package package;
  final Hall hall;
  final Extra extra;

  Map<String, Object?> toJson() => _$MxikToJson(this);
}
