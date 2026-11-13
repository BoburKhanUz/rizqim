// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch15.dart';
import 'store3.dart';
import 'device11.dart';
import 'modules2.dart';
import 'cart.dart';

part 'data225.g.dart';

@JsonSerializable()
class Data225 {
  const Data225({
    required this.branch,
    required this.store,
    required this.device,
    required this.tillUuid,
    required this.modules,
    required this.serverTime,
    required this.cart,
  });

  factory Data225.fromJson(Map<String, Object?> json) =>
      _$Data225FromJson(json);

  final Branch15 branch;
  final Store3? store;
  final Device11 device;
  @JsonKey(name: 'till_uuid')
  final String? tillUuid;
  final Modules2 modules;
  @JsonKey(name: 'server_time')
  final String serverTime;
  final Cart? cart;

  Map<String, Object?> toJson() => _$Data225ToJson(this);
}
