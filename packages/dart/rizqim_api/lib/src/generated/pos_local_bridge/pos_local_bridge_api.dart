// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_lan_response.dart';

part 'pos_local_bridge_api.g.dart';

@RestApi()
abstract class PosLocalBridgeApi {
  factory PosLocalBridgeApi(Dio dio, {String? baseUrl}) = _PosLocalBridgeApi;

  /// The terminal's LAN keys.
  ///
  /// The same `lan` section as GET /pos/bootstrap, without a staff grant (`grant: null`): the terminal's own device key,.
  /// the beacon key, the branch's hub and port (the hub also gets `hub_secret` and the terminals it lets in). Only a.
  /// terminal of a branch with its own token; a store-wide terminal gets `data: null`.
  @GET('/v1/pos/lan')
  Future<GetV1PosLanResponse> posLanShow();
}
