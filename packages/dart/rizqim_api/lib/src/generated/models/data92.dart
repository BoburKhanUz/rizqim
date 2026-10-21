// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device10.dart';
import 'store2.dart';
import 'branch10.dart';

part 'data92.g.dart';

@JsonSerializable()
class Data92 {
  const Data92({
    required this.device,
    required this.store,
    required this.branch,
  });

  factory Data92.fromJson(Map<String, Object?> json) => _$Data92FromJson(json);

  final Device10 device;
  final Store2? store;
  final Branch10? branch;

  Map<String, Object?> toJson() => _$Data92ToJson(this);
}
