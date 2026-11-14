// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'package2.dart';
import 'hall2.dart';
import 'extra2.dart';

part 'mxik2.g.dart';

@JsonSerializable()
class Mxik2 {
  const Mxik2({required this.package, required this.hall, required this.extra});

  factory Mxik2.fromJson(Map<String, Object?> json) => _$Mxik2FromJson(json);

  final Package2 package;
  final Hall2 hall;
  final Extra2 extra;

  Map<String, Object?> toJson() => _$Mxik2ToJson(this);
}
