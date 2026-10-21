// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device9.dart';
import 'store2.dart';
import 'branch3.dart';

part 'data63.g.dart';

@JsonSerializable()
class Data63 {
  const Data63({
    required this.device,
    required this.store,
    required this.branch,
  });

  factory Data63.fromJson(Map<String, Object?> json) => _$Data63FromJson(json);

  final Device9 device;
  final Store2? store;
  final Branch3? branch;

  Map<String, Object?> toJson() => _$Data63ToJson(this);
}
