// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device7.dart';
import 'store2.dart';
import 'branch3.dart';

part 'data62.g.dart';

@JsonSerializable()
class Data62 {
  const Data62({
    required this.device,
    required this.store,
    required this.branch,
  });

  factory Data62.fromJson(Map<String, Object?> json) => _$Data62FromJson(json);

  final Device7 device;
  final Store2? store;
  final Branch3? branch;

  Map<String, Object?> toJson() => _$Data62ToJson(this);
}
